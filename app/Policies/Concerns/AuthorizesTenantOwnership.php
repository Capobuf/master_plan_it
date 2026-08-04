<?php

namespace App\Policies\Concerns;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

trait AuthorizesTenantOwnership /** @phpstan-ignore trait.unused (Foundation policy boundary consumed by the first tenant-owned feature Policy.) */
{
    protected function authorizeTenantOwnership(
        User $actor,
        string $ability,
        ?TenantContext $context,
        Model $resource,
        PermissionRegistrar $permissionRegistrar,
        PlatformAdministrator $platformAdministrator,
    ): Response {
        $persistedActor = $actor->exists
            ? User::query()
                ->whereKey($actor->getKey())
                ->where('is_active', true)
                ->first()
            : null;

        if (! $persistedActor instanceof User || ! $this->hasExactAbility(
            $persistedActor,
            $ability,
            $permissionRegistrar,
            $platformAdministrator,
        )) {
            return Response::deny('PERMISSION_DENIED');
        }

        if (! $context instanceof TenantContext) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        if (! $context->actor->exists || $context->actor->getKey() !== $persistedActor->getKey()) {
            return Response::deny('PERMISSION_DENIED');
        }

        if ($persistedActor->tenant_id === null) {
            if (! $platformAdministrator->hasProtectedRole($persistedActor)) {
                return Response::deny('PERMISSION_DENIED');
            }
        } elseif ((int) $persistedActor->tenant_id !== $context->tenantId) {
            return Response::deny('PERMISSION_DENIED');
        }

        if (! $context->tenant->exists
            || $context->tenant->getKey() === null
            || (int) $context->tenant->getKey() !== $context->tenantId
            || ! Tenant::query()->whereKey($context->tenantId)->exists()
        ) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        $resourceExists = $resource->exists && $resource->getKey() !== null
            && $resource->newQuery()
                ->where($resource->qualifyColumn('tenant_id'), $context->tenantId)
                ->whereKey($resource->getKey())
                ->exists();

        if (! $resourceExists) {
            return Response::denyAsNotFound('RESOURCE_NOT_FOUND');
        }

        return Response::allow();
    }

    private function hasExactAbility(
        User $actor,
        string $ability,
        PermissionRegistrar $permissionRegistrar,
        PlatformAdministrator $platformAdministrator,
    ): bool {
        try {
            if ($actor->tenant_id === null) {
                return $platformAdministrator->allows($actor, $ability);
            }

            $currentTeamId = $permissionRegistrar->getPermissionsTeamId();

            return is_int($currentTeamId)
                && $currentTeamId === (int) $actor->tenant_id
                && $actor->checkPermissionTo($ability, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
