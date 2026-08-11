<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\PermissionRegistrar;

final class UserPolicy
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
        return $this->collection($user, 'tenant-users.view');
    }

    public function view(User $user, User $target): Response
    {
        return $this->record($user, $target, 'tenant-users.view');
    }

    public function create(User $user): Response
    {
        return $this->collection($user, 'tenant-users.manage');
    }

    public function allowsCreate(User $user): Response
    {
        return $this->create($user);
    }

    public function update(User $user, User $target): Response
    {
        return $this->record($user, $target, 'tenant-users.manage');
    }

    public function allowsUpdate(User $user, User $target): Response
    {
        return $this->update($user, $target);
    }

    public function deactivate(User $user, User $target): Response
    {
        $response = $this->record($user, $target, 'tenant-users.manage');
        if ($response->denied()) {
            return $response;
        }

        return User::query()->whereKey($target->getKey())
            ->where('tenant_id', $this->tenantContext->tenantId)->where('is_active', true)->exists()
            ? Response::allow() : Response::deny('USER_INACTIVE');
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $target): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, User $target): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function record(User $actor, User $target, string $ability): Response
    {
        return $this->authorizeTenantOwnership($actor, $ability, $this->tenantContext, $target, $this->permissionRegistrar, $this->platformAdministrator);
    }

    private function collection(User $actor, string $ability): Response
    {
        return $this->tenantAbilityAuthorizer->allows($actor, $this->tenantContext, $ability)
            ? Response::allow() : Response::deny('PERMISSION_DENIED');
    }
}
