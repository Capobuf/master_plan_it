<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesVendorMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateVendor
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, string $name, ?string $vatNumber, ?string $email, ?string $phone, ?string $address, int $expectedLockVersion, string $correlationId): Vendor
    {
        $this->policy($context)->update($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);
        $details = $this->validatedDetails($name, $vatNumber, $email, $phone, $address);

        return DB::transaction(function () use ($actor, $context, $correlationId, $details, $expectedLockVersion, $persistedActor, $target, $tenant): Vendor {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            $old = $vendor->only(array_keys($details));
            $vendor->fill($details);
            if (! $vendor->isDirty()) {
                return $vendor->refresh();
            }

            try {
                $vendor->forceFill(['lock_version' => $expectedLockVersion + 1])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The vendor name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Update, $correlationId, $vendor);
            $this->auditRecorder->record(
                eventType: 'vendor.updated',
                correlationId: $correlationId,
                properties: new AuditProperties(['old' => $old, 'new' => $details]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor->refresh();
        });
    }
}
