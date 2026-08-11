<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AssignTenantRoles
{
    public function __construct(
        private readonly TenantAbilityAuthorizer $tenantAbilityAuthorizer,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /** @param list<Role> $roles */
    public function execute(
        User $actor,
        TenantContext $context,
        User $target,
        array $roles,
        string $correlationId,
    ): User {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            return DB::transaction(function () use ($correlationId, $persistedActor, $roles, $target, $tenant): User {
                $user = $this->lockedTenantUser($target, (int) $tenant->getKey());
                $tenantRoles = $this->lockedTenantRoles($roles, (int) $tenant->getKey());
                $roleIds = array_map(static fn (Role $role): int => (int) $role->getKey(), $tenantRoles);
                sort($roleIds);

                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
                $user->syncRoles($tenantRoles);

                $this->auditRecorder->record(
                    eventType: 'tenant.user.roles-updated',
                    correlationId: $correlationId,
                    properties: new AuditProperties(['role_ids' => $roleIds]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $user,
                );

                return $user->refresh();
            });
        } finally {
            $target->unsetRelation('roles');
            $target->unsetRelation('permissions');
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $context->actor->unsetRelation('roles');
            $context->actor->unsetRelation('permissions');
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function lockedTenantUser(User $target, int $tenantId): User
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);
        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($originalKey)
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $user;
    }

    /**
     * @param  list<Role>  $roles
     * @return list<Role>
     */
    private function lockedTenantRoles(array $roles, int $tenantId): array
    {
        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => 'At least one tenant role is required.',
            ]);
        }

        $resolved = [];
        foreach ($roles as $index => $role) {
            if (! $role instanceof Role) {
                throw ValidationException::withMessages(["roles.{$index}" => 'The selected role is invalid.']);
            }

            $keyName = $role->getKeyName();
            $key = $role->getKey();
            $originalKey = $role->getRawOriginal($keyName);
            if (! $role->exists || $key === null || $key !== $originalKey) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }

            $persistedRole = Role::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($originalKey)
                ->lockForUpdate()
                ->first();

            if ($persistedRole === null) {
                if (Role::query()->whereNull('tenant_id')->whereKey($originalKey)->exists()) {
                    throw new DomainException('PLATFORM_ABILITY_PROTECTED');
                }

                throw new DomainException('TENANT_RELATION_MISMATCH');
            }

            $resolved[(string) $persistedRole->getKey()] = $persistedRole;
        }

        return array_values($resolved);
    }

    /** @return array{User, Tenant} */
    private function authorize(User $actor, TenantContext $context): array
    {
        return $this->tenantAbilityAuthorizer->authorize($actor, $context, 'tenant-users.manage');
    }
}
