<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePolicy
{
    use AuthorizesTenantOwnership;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->authorizeCollection($user);
    }

    public function view(User $user, Role $role): Response
    {
        return $this->authorizeRecord($user, $role);
    }

    public function create(User $user): Response
    {
        return $this->authorizeCollection($user);
    }

    public function allowsCreate(User $user): Response
    {
        return $this->create($user);
    }

    public function update(User $user, Role $role): Response
    {
        return $this->authorizeRecord($user, $role);
    }

    public function allowsUpdate(User $user, Role $role): Response
    {
        return $this->update($user, $role);
    }

    public function delete(User $user, Role $role): Response
    {
        return $this->authorizeRecord($user, $role);
    }

    public function allowsDelete(User $user, Role $role): Response
    {
        return $this->delete($user, $role);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Role $role): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function authorizeRecord(User $user, Role $role): Response
    {
        return $this->authorizeTenantOwnership(
            $user,
            'platform.roles.manage',
            $this->tenantContext,
            $role,
            $this->permissionRegistrar,
            $this->platformAdministrator,
        );
    }

    private function authorizeCollection(User $user): Response
    {
        $persistedUser = $this->persistedPlatformActor($user);

        if ($persistedUser === null || ! $this->platformAdministrator->allows($persistedUser, 'platform.roles.manage')) {
            return Response::deny('PERMISSION_DENIED');
        }

        if (! $this->contextMatches($persistedUser)) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        return Response::allow();
    }

    private function persistedPlatformActor(User $user): ?User
    {
        $key = $user->getKey();
        $originalKey = $user->getRawOriginal($user->getKeyName());

        if (! $user->exists || $key === null || $key !== $originalKey) {
            return null;
        }

        return User::query()
            ->whereKey($originalKey)
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->first();
    }

    private function contextMatches(User $persistedUser): bool
    {
        $contextActor = $this->tenantContext->actor;
        $contextTenant = $this->tenantContext->tenant;
        $actorKey = $contextActor->getKey();
        $actorOriginalKey = $contextActor->getRawOriginal($contextActor->getKeyName());
        $tenantKey = $contextTenant->getKey();
        $tenantOriginalKey = $contextTenant->getRawOriginal($contextTenant->getKeyName());

        return $contextActor->exists
            && $actorKey !== null
            && $actorKey === $actorOriginalKey
            && $actorKey === $persistedUser->getKey()
            && $contextTenant->exists
            && $tenantKey !== null
            && $tenantKey === $tenantOriginalKey
            && (int) $tenantKey === $this->tenantContext->tenantId
            && Tenant::query()->whereKey($tenantOriginalKey)->exists();
    }
}
