<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesVendorMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Models\Vendor;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeactivateVendor
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, int $expectedLockVersion, string $correlationId): Vendor
    {
        $this->policy($context)->deactivate($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): Vendor {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            if (! $vendor->active) {
                throw new DomainException('STALE_VERSION');
            }

            $vendor->fill(['active' => false, 'lock_version' => $expectedLockVersion + 1])->save();
            $this->recordRevision($actor, $context, RevisionOperation::Deactivate, $correlationId, $vendor);
            $this->auditRecorder->record(
                eventType: 'vendor.deactivated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old_active' => true, 'new_active' => false]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor->refresh();
        });
    }
}
