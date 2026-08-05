<?php

namespace App\Domain\MasterData\Actions\Concerns;

use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use App\Policies\VendorPolicy;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

trait ManagesVendorMutation
{
    private function policy(TenantContext $context): VendorPolicy
    {
        return new VendorPolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function activeContextTenant(TenantContext $context): Tenant
    {
        $key = $context->tenant->getKey();
        $originalKey = $context->tenant->getRawOriginal($context->tenant->getKeyName());

        if (! $context->tenant->exists
            || $key === null
            || $originalKey === null
            || $key !== $originalKey
            || (int) $originalKey !== $context->tenantId) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $tenant = Tenant::query()->whereKey($originalKey)->first();
        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }

        return $tenant;
    }

    private function persistedActiveActor(User $actor): User
    {
        $keyName = $actor->getKeyName();
        $key = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);

        if (! $actor->exists || $key === null || $key !== $originalKey) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $persistedActor = User::query()
            ->whereKey($originalKey)
            ->where('is_active', true)
            ->first();

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $persistedActor;
    }

    /** @return Collection<int, Vendor> */
    private function lockTenantVendors(Tenant $tenant): Collection
    {
        return Vendor::query()
            ->where('tenant_id', $tenant->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Vendor $vendor): int => (int) $vendor->getKey());
    }

    /** @param Collection<int, Vendor> $vendors */
    private function lockedTarget(Vendor $target, Collection $vendors): Vendor
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);

        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('STALE_VERSION');
        }

        $vendor = $vendors->get((int) $originalKey);

        if (! $vendor instanceof Vendor) {
            throw new DomainException('STALE_VERSION');
        }

        return $vendor;
    }

    /**
     * @return array{name: string, vat_number: ?string, email: ?string, phone: ?string, address: ?string}
     */
    private function validatedDetails(string $name, ?string $vatNumber, ?string $email, ?string $phone, ?string $address): array
    {
        $fields = [
            'name' => $this->requiredText($name, 'name', 255),
            'vat_number' => $this->optionalText($vatNumber, 'vat_number', 255),
            'email' => $this->optionalText($email, 'email', 255),
            'phone' => $this->optionalText($phone, 'phone', 255),
            'address' => $this->optionalText($address, 'address', 65535),
        ];

        if ($fields['email'] !== null && filter_var($fields['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'email' => 'The vendor email must be a valid email address.',
            ]);
        }

        return $fields;
    }

    private function requiredText(string $value, string $field, int $maximumLength): string
    {
        if (trim($value) === '' || mb_strlen($value) > $maximumLength) {
            throw ValidationException::withMessages([
                $field => "The vendor {$field} must be between 1 and {$maximumLength} characters.",
            ]);
        }

        return $value;
    }

    private function optionalText(?string $value, string $field, int $maximumLength): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength) {
            throw ValidationException::withMessages([
                $field => "The vendor {$field} may not be greater than {$maximumLength} characters.",
            ]);
        }

        return $value;
    }

    private function assertExpectedVersion(Vendor $vendor, int $expectedLockVersion): void
    {
        if ($vendor->lock_version !== $expectedLockVersion) {
            throw new DomainException('STALE_VERSION');
        }
    }

    private function recordRevision(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        Vendor $vendor,
        ?int $restoredFromVersionId = null,
    ): void {
        $batch = $this->beginRevisionBatch(
            $actor,
            $context,
            $operation,
            $correlationId,
            $vendor,
            $restoredFromVersionId,
        );
        $version = $vendor->latestVersions()->firstOrFail();

        if (! $version instanceof Version) {
            throw new DomainException('REVISION_RESTORE_INVALID');
        }

        $this->linkRevisionSnapshot($batch, $version);
    }

    private function beginRevisionBatch(
        User $actor,
        TenantContext $context,
        RevisionOperation $operation,
        string $correlationId,
        Vendor $vendor,
        ?int $restoredFromVersionId = null,
    ): RevisionBatch {
        return app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            $operation,
            null,
            $correlationId,
            $vendor,
            $restoredFromVersionId,
        );
    }

    private function linkRevisionSnapshot(RevisionBatch $batch, Version $version): void
    {
        app(LinkVersionToRevisionBatch::class)->execute($batch, $version, 1);
    }
}
