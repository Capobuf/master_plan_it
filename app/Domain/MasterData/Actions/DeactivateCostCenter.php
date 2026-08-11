<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesCostCenterMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeactivateCostCenter
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, int $expectedLockVersion, string $correlationId): CostCenter
    {
        $this->policy($context)->deactivate($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            if (! $costCenter->active) {
                throw new DomainException('STALE_VERSION');
            }
            $this->assertNoActiveDescendants($costCenter, $costCenters);

            $costCenter->fill(['active' => false, 'lock_version' => $expectedLockVersion + 1])->save();
            $this->recordRevision($actor, $context, RevisionOperation::Deactivate, $correlationId, $costCenter);
            $this->auditRecorder->record(
                eventType: 'cost-center.deactivated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old_active' => true, 'new_active' => false]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter->refresh();
        });
    }
}
