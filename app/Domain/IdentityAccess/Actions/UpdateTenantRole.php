<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateTenantRole
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /** @param list<string> $abilities */
    public function execute(
        User $actor,
        TenantContext $context,
        Role $target,
        string $name,
        array $abilities,
        string $correlationId,
    ): Role {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            return DB::transaction(function () use ($abilities, $correlationId, $name, $persistedActor, $target, $tenant): Role {
                $role = $this->lockedTenantRole($target, (int) $tenant->getKey());
                $validatedName = $this->validatedName($name, (int) $tenant->getKey(), $role);
                $permissions = $this->validatedPermissions($abilities);

                try {
                    $role->forceFill(['name' => $validatedName])->save();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'name' => 'The tenant role name has already been taken.',
                    ]);
                }

                $role->syncPermissions($permissions);

                $this->auditRecorder->record(
                    eventType: 'tenant.role.updated',
                    correlationId: $correlationId,
                    properties: new AuditProperties(['changed_fields' => ['name', 'abilities']]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $role,
                );

                return $role->refresh();
            });
        } finally {
            $target->unsetRelation('permissions');
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $context->actor->unsetRelation('roles');
            $context->actor->unsetRelation('permissions');
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function validatedName(string $name, int $tenantId, Role $role): string
    {
        $name = trim($name);
        Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
        ])->validate();

        if (Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->where('name', $name)
            ->whereKeyNot($role->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'The tenant role name has already been taken.',
            ]);
        }

        return $name;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<Permission>
     */
    private function validatedPermissions(array $abilities): array
    {
        Validator::make(['abilities' => $abilities], [
            'abilities' => ['array'],
            'abilities.*' => ['required', 'string', 'distinct'],
        ])->validate();

        if (array_intersect(PermissionCatalogue::protectedAbilities(), $abilities) !== []) {
            throw new DomainException('PLATFORM_ABILITY_PROTECTED');
        }

        foreach ($abilities as $index => $ability) {
            if (! PermissionCatalogue::isTenant($ability)) {
                throw ValidationException::withMessages([
                    "abilities.{$index}" => 'The selected ability is not in the permission catalogue.',
                ]);
            }
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $abilities)
            ->get()
            ->keyBy('name');

        foreach ($abilities as $index => $ability) {
            if (! $permissions->has($ability)) {
                throw ValidationException::withMessages([
                    "abilities.{$index}" => 'The selected ability is not in the permission catalogue.',
                ]);
            }
        }

        return array_values(array_map(
            static fn (string $ability): Permission => $permissions->get($ability),
            $abilities,
        ));
    }

    private function lockedTenantRole(Role $target, int $tenantId): Role
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);

        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $role = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($originalKey)
            ->lockForUpdate()
            ->first();

        if ($role !== null) {
            return $role;
        }

        if (Role::query()->whereNull('tenant_id')->whereKey($originalKey)->exists()) {
            throw new DomainException('PLATFORM_ABILITY_PROTECTED');
        }

        throw new DomainException('TENANT_RELATION_MISMATCH');
    }

    /** @return array{User, Tenant} */
    private function authorize(User $actor, TenantContext $context): array
    {
        $persistedActor = $this->persistedActor($actor);
        if ($persistedActor === null || ! $this->platformAdministrator->allows($persistedActor, 'platform.roles.manage')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        if ($this->persistedActor($context->actor)?->getKey() !== $persistedActor->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $tenant = $this->persistedContextTenant($context);
        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        return [$persistedActor, $tenant];
    }

    private function persistedActor(User $actor): ?User
    {
        $keyName = $actor->getKeyName();
        $key = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);
        if (! $actor->exists || $key === null || $key !== $originalKey) {
            return null;
        }

        return User::query()->whereKey($originalKey)->whereNull('tenant_id')->where('is_active', true)->first();
    }

    private function persistedContextTenant(TenantContext $context): ?Tenant
    {
        $keyName = $context->tenant->getKeyName();
        $key = $context->tenant->getKey();
        $originalKey = $context->tenant->getRawOriginal($keyName);
        if (! $context->tenant->exists || $key === null || $key !== $originalKey || (int) $key !== $context->tenantId) {
            return null;
        }

        return Tenant::query()->whereKey($originalKey)->first();
    }
}
