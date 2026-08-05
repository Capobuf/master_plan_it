<?php

namespace App\Domain\MasterData\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

final class VendorListQuery
{
    /** @return Builder<Vendor> */
    public function forTenant(User $actor, TenantContext $context): Builder
    {
        $this->policy($context)->viewAny($actor)->authorize();
        $this->assertActiveContextTenant($context);

        return TenantOwnedRecordQuery::forTenant($context, Vendor::class)
            ->orderByRaw('LOWER(name)')
            ->orderBy('id');
    }

    private function policy(TenantContext $context): VendorPolicy
    {
        return new VendorPolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function assertActiveContextTenant(TenantContext $context): void
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
    }
}
