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

final class ReactivateVendor
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, int $expectedLockVersion, string $correlationId): Vendor
    {
        $this->policy($context)->reactivate($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): Vendor {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            if ($vendor->active) {
                throw new DomainException('STALE_VERSION');
            }

            $vendor->fill(['active' => true, 'lock_version' => $expectedLockVersion + 1])->save();
            $this->recordRevision($actor, $context, RevisionOperation::Reactivate, $correlationId, $vendor);
            $this->auditRecorder->record(
                eventType: 'vendor.reactivated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old_active' => false, 'new_active' => true]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor->refresh();
        });
    }
}
