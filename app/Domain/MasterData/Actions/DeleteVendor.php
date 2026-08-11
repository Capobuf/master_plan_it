<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesVendorMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Overtrue\LaravelVersionable\VersionStrategy;

final class DeleteVendor
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, int $expectedLockVersion, string $correlationId): void
    {
        $this->policy($context)->delete($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): void {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            if ($this->hasDomainReference($vendor)) {
                throw new DomainException('REFERENCED_RECORD_DELETE_DENIED');
            }

            $batch = $this->beginRevisionBatch($actor, $context, RevisionOperation::Delete, $correlationId, $vendor);
            $vendor->delete();
            $deleteSnapshot = $vendor->createVersion(
                $vendor->getVersionableAttributes(VersionStrategy::SNAPSHOT),
            );

            if (! $deleteSnapshot instanceof Version) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }

            $this->linkRevisionSnapshot($batch, $deleteSnapshot);
            $this->auditRecorder->record(
                eventType: 'vendor.deleted',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'name' => $vendor->name,
                    'delete_snapshot_version_id' => $deleteSnapshot->getKey(),
                ]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );
        });
    }

    private function hasDomainReference(Vendor $vendor): bool
    {
        return Schema::hasTable('expense_rows')
            && DB::table('expense_rows')
                ->where('tenant_id', $vendor->tenant_id)
                ->where('vendor_id', $vendor->getKey())
                ->exists();
    }
}
