<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesCostCenterMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCostCenter
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, string $name, ?CostCenter $parent, int $expectedLockVersion, string $correlationId): CostCenter
    {
        $this->policy($context)->update($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);
        $name = $this->validatedName($name);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $name, $parent, $persistedActor, $target, $tenant): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            $parentId = $this->parentId($parent, $costCenters);
            $this->assertValidHierarchy($costCenters, (int) $costCenter->getKey(), $parentId);
            $old = ['name' => $costCenter->name, 'parent_id' => $costCenter->parent_id];
            $costCenter->fill(['name' => $name, 'parent_id' => $parentId]);
            if (! $costCenter->isDirty()) {
                return $costCenter->refresh();
            }

            try {
                $costCenter->forceFill(['lock_version' => $expectedLockVersion + 1])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The cost center name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Update, $correlationId, $costCenter);
            $this->auditRecorder->record(
                eventType: 'cost-center.updated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old' => $old, 'new' => ['name' => $name, 'parent_id' => $parentId]]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter->refresh();
        });
    }
}
