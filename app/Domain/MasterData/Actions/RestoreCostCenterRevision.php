<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesCostCenterMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreCostCenterRevision
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, RevisionBatch $source, int $expectedLockVersion, string $correlationId): CostCenter
    {
        $this->policy($context)->restoreRevision($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $source, $target, $tenant): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            $logical = app(OperationalRevisionQuery::class);
            $batch = $logical->findVisibleBatch($context, $costCenter, (int) $source->getKey());
            $contents = $logical->snapshot($context, $costCenter, $batch)[$costCenter->getMorphClass()][(int) $costCenter->getKey()] ?? null;
            if (! is_array($contents)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $parentId = array_key_exists('parent_id', $contents) && $contents['parent_id'] !== null
                ? (int) $contents['parent_id']
                : null;
            $this->assertValidHierarchy($costCenters, (int) $costCenter->getKey(), $parentId);
            $name = $this->validatedName((string) ($contents['name'] ?? ''));

            try {
                $costCenter->fill([
                    'name' => $name,
                    'parent_id' => $parentId,
                    'active' => (bool) ($contents['active'] ?? false),
                ]);
                if (! $costCenter->isDirty()) {
                    throw new DomainException('REVISION_RESTORE_INVALID');
                }
                $costCenter->forceFill(['lock_version' => $expectedLockVersion + 1])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The restored cost center name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Restore, $correlationId, $costCenter, (int) $batch->getKey());
            $this->auditRecorder->record(
                eventType: 'cost-center.restored',
                correlationId: $correlationId,
                properties: new AuditProperties(['restored_from_batch_id' => $batch->getKey()]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter->refresh();
        });
    }
}
