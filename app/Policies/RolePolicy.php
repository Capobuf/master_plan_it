<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Authorization\TenantAbilityAuthorizer;
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
        private readonly TenantAbilityAuthorizer $tenantAbilityAuthorizer,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->collection($user, 'tenant-roles.view');
    }

    public function view(User $user, Role $role): Response
    {
        return $this->record($user, $role, 'tenant-roles.view');
    }

    public function create(User $user): Response
    {
        return $this->collection($user, 'tenant-roles.manage');
    }

    public function allowsCreate(User $user): Response
    {
        return $this->create($user);
    }

    public function update(User $user, Role $role): Response
    {
        return $this->record($user, $role, 'tenant-roles.manage');
    }

    public function allowsUpdate(User $user, Role $role): Response
    {
        return $this->update($user, $role);
    }

    public function delete(User $user, Role $role): Response
    {
        return $this->record($user, $role, 'tenant-roles.manage');
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

    private function record(User $actor, Role $role, string $ability): Response
    {
        return $this->authorizeTenantOwnership($actor, $ability, $this->tenantContext, $role, $this->permissionRegistrar, $this->platformAdministrator);
    }

    private function collection(User $actor, string $ability): Response
    {
        return $this->tenantAbilityAuthorizer->allows($actor, $this->tenantContext, $ability)
            ? Response::allow() : Response::deny('PERMISSION_DENIED');
    }
}
