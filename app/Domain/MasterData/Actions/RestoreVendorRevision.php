<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Actions\Concerns\ManagesVendorMutation;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\RevisionBatch;
use App\Models\User;
use App\Models\Vendor;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreVendorRevision
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, RevisionBatch $source, int $expectedLockVersion, string $correlationId): Vendor
    {
        $this->policy($context)->restoreRevision($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $source, $target, $tenant): Vendor {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            $logical = app(OperationalRevisionQuery::class);
            $batch = $logical->findVisibleBatch($context, $vendor, (int) $source->getKey());
            $contents = $logical->snapshot($context, $vendor, $batch)[$vendor->getMorphClass()][(int) $vendor->getKey()] ?? null;
            if (! is_array($contents)) {
                throw new DomainException('REVISION_RESTORE_INVALID');
            }
            $details = $this->validatedDetails(
                (string) ($contents['name'] ?? ''),
                $this->nullableString($contents['vat_number'] ?? null),
                $this->nullableString($contents['email'] ?? null),
                $this->nullableString($contents['phone'] ?? null),
                $this->nullableString($contents['address'] ?? null),
            );

            try {
                $vendor->fill([
                    ...$details,
                    'active' => (bool) ($contents['active'] ?? false),
                ]);
                if (! $vendor->isDirty()) {
                    throw new DomainException('REVISION_RESTORE_INVALID');
                }
                $vendor->forceFill(['lock_version' => $expectedLockVersion + 1])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The restored vendor name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Restore, $correlationId, $vendor, (int) $batch->getKey());
            $this->auditRecorder->record(
                eventType: 'vendor.restored',
                correlationId: $correlationId,
                properties: new AuditProperties(['restored_from_batch_id' => $batch->getKey()]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor->refresh();
        });
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
