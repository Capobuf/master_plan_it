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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Overtrue\LaravelVersionable\VersionStrategy;

final class DeleteCostCenter
{
    use ManagesCostCenterMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, CostCenter $target, int $expectedLockVersion, string $correlationId): void
    {
        $this->policy($context)->delete($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): void {
            $costCenters = $this->lockTenantCostCenters($tenant);
            $costCenter = $this->lockedTarget($target, $costCenters);
            $this->assertExpectedVersion($costCenter, $expectedLockVersion);
            $this->assertNoDescendants($costCenter, $costCenters);
            if ($this->hasDomainReference($costCenter)) {
                throw new DomainException('REFERENCED_RECORD_DELETE_DENIED');
            }

            $batch = $this->beginRevisionBatch($actor, $context, RevisionOperation::Delete, $correlationId, $costCenter);
            $costCenter->delete();
            $deleteSnapshot = $costCenter->createVersion(
                $costCenter->getVersionableAttributes(VersionStrategy::SNAPSHOT),
            );

            if (! $deleteSnapshot instanceof Version) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }

            $this->linkRevisionSnapshot($batch, $deleteSnapshot);
            $this->auditRecorder->record(
                eventType: 'cost-center.deleted',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'name' => $costCenter->name,
                    'delete_snapshot_version_id' => $deleteSnapshot->getKey(),
                ]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $costCenter,
            );
        });
    }

    private function hasDomainReference(CostCenter $costCenter): bool
    {
        return Schema::hasTable('expenses')
            && DB::table('expenses')
                ->where('tenant_id', $costCenter->tenant_id)
                ->where('cost_center_id', $costCenter->getKey())
                ->exists();
    }
}
