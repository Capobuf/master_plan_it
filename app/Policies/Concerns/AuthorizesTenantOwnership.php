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

trait AuthorizesTenantOwnership
{
    protected function authorizeTenantOwnership(
        User $actor,
        string $ability,
        ?TenantContext $context,
        Model $resource,
        PermissionRegistrar $permissionRegistrar,
        PlatformAdministrator $platformAdministrator,
    ): Response {
        $actorKey = $actor->getKey();
        $actorOriginalKey = $actor->getRawOriginal($actor->getKeyName());
        $persistedActor = $actor->exists
            && $actorKey !== null
            && $actorKey === $actorOriginalKey
            ? User::query()
                ->whereKey($actorOriginalKey)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $persistedActor instanceof User || ! $this->hasTenantOwnershipExactAbility(
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

        $contextActorKey = $context->actor->getKey();
        $contextActorOriginalKey = $context->actor->getRawOriginal($context->actor->getKeyName());

        if (! $context->actor->exists
            || $contextActorKey === null
            || $contextActorKey !== $contextActorOriginalKey
            || $contextActorKey !== $persistedActor->getKey()) {
            return Response::deny('PERMISSION_DENIED');
        }

        if ($persistedActor->tenant_id === null) {
            if (! $platformAdministrator->hasProtectedRole($persistedActor)) {
                return Response::deny('PERMISSION_DENIED');
            }
        } elseif ((int) $persistedActor->tenant_id !== $context->tenantId) {
            return Response::deny('PERMISSION_DENIED');
        }

        $tenantKey = $context->tenant->getKey();
        $tenantOriginalKey = $context->tenant->getRawOriginal($context->tenant->getKeyName());

        if (! $context->tenant->exists
            || $tenantKey === null
            || $tenantOriginalKey === null
            || $tenantKey !== $tenantOriginalKey
            || (int) $tenantOriginalKey !== $context->tenantId
            || ! Tenant::query()->whereKey($tenantOriginalKey)->exists()
        ) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        $resourceKey = $resource->getKey();
        $resourceOriginalKey = $resource->getRawOriginal($resource->getKeyName());

        $resourceExists = $resource->exists
            && $resourceKey !== null
            && $resourceOriginalKey !== null
            && $resourceKey === $resourceOriginalKey
            && $resource->newQuery()
                ->where($resource->qualifyColumn('tenant_id'), $context->tenantId)
                ->whereKey($resourceOriginalKey)
                ->exists();

        if (! $resourceExists) {
            return Response::denyAsNotFound('RESOURCE_NOT_FOUND');
        }

        return Response::allow();
    }

    private function hasTenantOwnershipExactAbility(
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
