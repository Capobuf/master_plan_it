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

final class CreateVendor
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, string $name, ?string $vatNumber, ?string $email, ?string $phone, ?string $address, string $correlationId): Vendor
    {
        $this->policy($context)->create($actor)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);
        $details = $this->validatedDetails($name, $vatNumber, $email, $phone, $address);

        return DB::transaction(function () use ($actor, $context, $correlationId, $details, $persistedActor, $tenant): Vendor {
            try {
                $vendor = Vendor::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    ...$details,
                    'active' => true,
                    'lock_version' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The vendor name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Create, $correlationId, $vendor);
            $this->auditRecorder->record(
                eventType: 'vendor.created',
                correlationId: $correlationId,
                properties: new AuditProperties([...$details, 'active' => true]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor;
        });
    }
}
