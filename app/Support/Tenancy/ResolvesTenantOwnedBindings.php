<?php

namespace App\Support\Tenancy;

use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

trait ResolvesTenantOwnedBindings /** @phpstan-ignore trait.unused (Foundation API consumed by the first concrete tenant-owned model.) */
{
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $context = request()->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return $query
            ->where($this->qualifyColumn('tenant_id'), $context->tenantId)
            ->where($field ?? $this->getRouteKeyName(), $value);
    }
}
