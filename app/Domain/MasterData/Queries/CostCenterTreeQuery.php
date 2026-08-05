<?php

namespace App\Domain\MasterData\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CostCenterPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

final class CostCenterTreeQuery
{
    /** @return Collection<int, CostCenter> */
    public function forTenant(User $actor, TenantContext $context): Collection
    {
        $costCenters = $this->builderForTenant($actor, $context)->get();
        $byParent = $costCenters->groupBy('parent_id');

        return $this->branch(null, $byParent);
    }

    /** @return Builder<CostCenter> */
    public function builderForTenant(User $actor, TenantContext $context): Builder
    {
        $this->policy($context)->viewAny($actor)->authorize();
        $this->assertActiveContextTenant($context);

        return TenantOwnedRecordQuery::forTenant($context, CostCenter::class)
            ->orderByRaw('LOWER(name)')
            ->orderBy('id');
    }

    /** @param \Illuminate\Support\Collection<int|string, Collection<int, CostCenter>> $byParent
     * @return Collection<int, CostCenter>
     */
    private function branch(?int $parentId, \Illuminate\Support\Collection $byParent): Collection
    {
        $children = $byParent->get($parentId ?? '', new Collection);

        foreach ($children as $costCenter) {
            $costCenter->setRelation('children', $this->branch((int) $costCenter->getKey(), $byParent));
        }

        return $children->values();
    }

    private function policy(TenantContext $context): CostCenterPolicy
    {
        return new CostCenterPolicy(
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
