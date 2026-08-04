<?php

namespace App\Support\Authorization;

use App\Models\User;
use Closure;
use DomainException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PlatformAdministrator
{
    public const int PLATFORM_TEAM_ID = 0;

    private const string ROLE_NAME = 'Administrator';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function assign(User $user): void
    {
        $persistedUser = $this->persistedEligibleUser($user);

        if ($persistedUser === null) {
            throw new DomainException('The protected Administrator role requires an active tenantless user.');
        }

        $this->withinPlatformScope($persistedUser, function () use ($persistedUser): void {
            $role = Role::query()
                ->where('name', self::ROLE_NAME)
                ->where('guard_name', 'web')
                ->whereNull('tenant_id')
                ->firstOrFail();

            $persistedUser->assignRole($role);
        });
    }

    public function hasProtectedRole(User $user): bool
    {
        $persistedUser = $this->persistedEligibleUser($user);

        if ($persistedUser === null) {
            return false;
        }

        return $this->withinPlatformScope(
            $persistedUser,
            function () use ($persistedUser): bool {
                $role = $this->globalRole();

                return $role !== null && $persistedUser->hasRole($role, 'web');
            },
        );
    }

    public function allows(User $user, string $ability): bool
    {
        $persistedUser = $this->persistedEligibleUser($user);

        if ($persistedUser === null) {
            return false;
        }

        return $this->withinPlatformScope(
            $persistedUser,
            function () use ($persistedUser, $ability): bool {
                $role = $this->globalRole();

                return $role !== null
                    && $persistedUser->hasRole($role, 'web')
                    && $persistedUser->checkPermissionTo($ability, 'web');
            },
        );
    }

    private function persistedEligibleUser(User $user): ?User
    {
        $keyName = $user->getKeyName();
        $currentKey = $user->getKey();
        $originalKey = $user->getRawOriginal($keyName);

        if (
            ! $user->exists
            || (! is_int($currentKey) && ! is_string($currentKey))
            || (! is_int($originalKey) && ! is_string($originalKey))
            || $currentKey !== $originalKey
        ) {
            return null;
        }

        $persistedUser = User::query()->find($originalKey);

        if (
            $persistedUser === null
            || ! $persistedUser->exists
            || $persistedUser->getKey() !== $originalKey
            || $persistedUser->tenant_id !== null
            || ! $persistedUser->is_active
        ) {
            return null;
        }

        return $persistedUser;
    }

    private function globalRole(): ?Role
    {
        return Role::query()
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', 'web')
            ->whereNull('tenant_id')
            ->first();
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    private function withinPlatformScope(User $user, Closure $operation): mixed
    {
        $previousTeamId = $this->registrar->getPermissionsTeamId();

        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $this->registrar->setPermissionsTeamId(self::PLATFORM_TEAM_ID);

        try {
            return $operation();
        } finally {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
            $this->registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
