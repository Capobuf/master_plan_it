<?php

namespace App\Policies;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOwnership;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\Response;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class PlanningYearPolicy
{
    use AuthorizesTenantOwnership;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function viewAny(User $user): Response
    {
        return $this->authorizeCollection($user, 'planning-year.view');
    }

    public function view(User $user, PlanningYear $planningYear): Response
    {
        return $this->authorizeRecord($user, 'planning-year.view', $planningYear);
    }

    public function create(User $user): Response
    {
        return $this->authorizeCollection($user, 'planning-year.create');
    }

    public function update(User $user, PlanningYear $planningYear): Response
    {
        return $this->authorizeRecord($user, 'planning-year.update', $planningYear);
    }

    public function deactivate(User $user, PlanningYear $planningYear): Response
    {
        return $this->authorizeRecord($user, 'planning-year.deactivate', $planningYear);
    }

    public function reactivate(User $user, PlanningYear $planningYear): Response
    {
        return $this->authorizeRecord($user, 'planning-year.reactivate', $planningYear);
    }

    private function authorizeRecord(User $user, string $ability, PlanningYear $planningYear): Response
    {
        $response = $this->authorizeTenantOwnership(
            $user,
            $ability,
            $this->tenantContext,
            $planningYear,
            $this->permissionRegistrar,
            $this->platformAdministrator,
        );

        return $response->denied() ? $response : $this->authorizeActiveContextTenant();
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

        return $this->authorizeActiveContextTenant();
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

    private function authorizeActiveContextTenant(): Response
    {
        $contextTenant = $this->tenantContext->tenant;
        $keyName = $contextTenant->getKeyName();
        $key = $contextTenant->getKey();
        $originalKey = $contextTenant->getRawOriginal($keyName);

        if (! $contextTenant->exists || $key === null || $key !== $originalKey) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        $persistedTenant = Tenant::query()->whereKey($originalKey)->first();

        if ($persistedTenant === null) {
            return Response::deny('TENANT_CONTEXT_REQUIRED');
        }

        return $persistedTenant->state === TenantState::Active
            || $this->platformAdministrator->hasProtectedRole($this->tenantContext->actor)
            ? Response::allow()
            : Response::deny('TENANT_INACTIVE');
    }
}
