<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class VendorPolicy
{
    use AuthorizesTenantOwnership;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->authorizeCollection($user, 'vendor.view');
    }

    public function view(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.view', $vendor);
    }

    public function create(User $user): Response
    {
        return $this->authorizeCollection($user, 'vendor.create');
    }

    public function update(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.update', $vendor);
    }

    public function delete(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.delete', $vendor);
    }

    public function deactivate(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.deactivate', $vendor);
    }

    public function reactivate(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.reactivate', $vendor);
    }

    public function viewRevisions(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.view-revisions', $vendor);
    }

    public function restoreRevision(User $user, Vendor $vendor): Response
    {
        return $this->authorizeRecord($user, 'vendor.restore-revision', $vendor);
    }

    private function authorizeRecord(User $user, string $ability, Vendor $vendor): Response
    {
        return $this->authorizeTenantOwnership(
            $user,
            $ability,
            $this->tenantContext,
            $vendor,
            $this->permissionRegistrar,
            $this->platformAdministrator,
        );
    }

    private function authorizeCollection(User $user, string $ability): Response
    {
        $persistedUser = $this->persistedActiveUser($user);

        if ($persistedUser === null || ! $this->hasExactAbility($persistedUser, $ability)) {
            return Response::deny('PERMISSION_DENIED');
        }

        if (! $this->contextMatches($persistedUser)) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        return Response::allow();
    }

    private function persistedActiveUser(User $user): ?User
    {
        $keyName = $user->getKeyName();
        $key = $user->getKey();
        $originalKey = $user->getRawOriginal($keyName);

        if (! $user->exists || $key === null || $key !== $originalKey) {
            return null;
        }

        return User::query()
            ->whereKey($originalKey)
            ->where('is_active', true)
            ->first();
    }

    private function hasExactAbility(User $user, string $ability): bool
    {
        try {
            if ($user->tenant_id === null) {
                return $this->platformAdministrator->allows($user, $ability);
            }

            return $this->permissionRegistrar->getPermissionsTeamId() === (int) $user->tenant_id
                && $user->checkPermissionTo($ability, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function contextMatches(User $user): bool
    {
        $contextActor = $this->tenantContext->actor;
        $contextTenant = $this->tenantContext->tenant;

        $actorKey = $contextActor->getKey();
        $actorOriginalKey = $contextActor->getRawOriginal($contextActor->getKeyName());
        $tenantKey = $contextTenant->getKey();
        $tenantOriginalKey = $contextTenant->getRawOriginal($contextTenant->getKeyName());

        if (! $contextActor->exists
            || $actorKey === null
            || $actorKey !== $actorOriginalKey
            || $actorKey !== $user->getKey()) {
            return false;
        }

        if (! $contextTenant->exists
            || $tenantKey === null
            || $tenantKey !== $tenantOriginalKey
            || (int) $tenantKey !== $this->tenantContext->tenantId
            || ! Tenant::query()->whereKey($tenantOriginalKey)->exists()) {
            return false;
        }

        return $user->tenant_id === null || (int) $user->tenant_id === $this->tenantContext->tenantId;
    }
}
