<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesCostCenterMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\User;
use App\Models\Version;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreCostCenterRevision
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, Version $version, int $expectedLockVersion, string $correlationId): CostCenter
    {
        $this->policy($context)->restoreRevision($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant, $version): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            $persistedVersion = $this->persistedTargetVersion($version, $costCenter);
            $contents = $persistedVersion->contents;
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
                    'lock_version' => $expectedLockVersion + 1,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The restored cost center name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Restore, $correlationId, $costCenter, (int) $persistedVersion->getKey());
            $this->auditRecorder->record(
                eventType: 'cost-center.restored',
                correlationId: $correlationId,
                properties: new AuditProperties(['restored_from_version_id' => $persistedVersion->getKey()]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter->refresh();
        });
    }

    private function persistedTargetVersion(Version $version, CostCenter $target): Version
    {
        $key = $version->getKey();
        $originalKey = $version->getRawOriginal($version->getKeyName());

        if (! $version->exists || $key === null || $originalKey === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persisted = Version::query()
            ->whereKey($originalKey)
            ->where('versionable_type', $target->getMorphClass())
            ->where('versionable_id', $target->getKey())
            ->first();

        if (! $persisted instanceof Version) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persisted;
    }
}
