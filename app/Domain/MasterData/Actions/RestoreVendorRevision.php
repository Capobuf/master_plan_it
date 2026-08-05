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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreVendorRevision
{
    use ManagesVendorMutation;

    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(User $actor, TenantContext $context, Vendor $target, Version $version, int $expectedLockVersion, string $correlationId): Vendor
    {
        $this->policy($context)->restoreRevision($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant, $version): Vendor {
            $vendor = $this->lockedTarget($target, $this->lockTenantVendors($tenant));
            $this->assertExpectedVersion($vendor, $expectedLockVersion);
            $persistedVersion = $this->persistedTargetVersion($version, $vendor);
            $contents = $persistedVersion->contents;
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
                    'lock_version' => $expectedLockVersion + 1,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'The restored vendor name already exists for the current tenant.',
                ]);
            }

            $this->recordRevision($actor, $context, RevisionOperation::Restore, $correlationId, $vendor, (int) $persistedVersion->getKey());
            $this->auditRecorder->record(
                eventType: 'vendor.restored',
                correlationId: $correlationId,
                properties: new AuditProperties(['restored_from_version_id' => $persistedVersion->getKey()]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $vendor,
            );

            return $vendor->refresh();
        });
    }

    private function persistedTargetVersion(Version $version, Vendor $target): Version
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
