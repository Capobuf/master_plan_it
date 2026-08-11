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

final class ReactivateCostCenter
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, int $expectedLockVersion, string $correlationId): CostCenter
    {
        $this->policy($context)->reactivate($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): CostCenter {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            if ($costCenter->active) {
                throw new DomainException('STALE_VERSION');
            }

            $costCenter->fill(['active' => true, 'lock_version' => $expectedLockVersion + 1])->save();
            $this->recordRevision($actor, $context, RevisionOperation::Reactivate, $correlationId, $costCenter);
            $this->auditRecorder->record(
                eventType: 'cost-center.reactivated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old_active' => false, 'new_active' => true]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );

            return $costCenter->refresh();
        });
    }
}
