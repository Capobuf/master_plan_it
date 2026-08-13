<?php

namespace App\Domain\Tenancy\Services;

use App\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

/** Tenant locks are always the first database lock in Tenant-scoped mutations. */
final class TenantMutationLock
{
    public function shared(int $tenantId): Tenant
    {
        return $this->lock($tenantId, false);
    }

    public function exclusive(int $tenantId): Tenant
    {
        return $this->lock($tenantId, true);
    }

    private function lock(int $tenantId, bool $exclusive): Tenant
    {
        $query = Tenant::query()->whereKey($tenantId);
        $tenant = $exclusive
            ? $query->lockForUpdate()->first()
            : $query->sharedLock()->first();

        if (! $tenant instanceof Tenant) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return $tenant;
    }
}
