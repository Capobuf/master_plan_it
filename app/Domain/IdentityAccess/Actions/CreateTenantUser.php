<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class CreateTenantUser
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /** @param list<Role> $roles */
    public function execute(
        User $actor,
        TenantContext $context,
        string $name,
        string $email,
        string $password,
        array $roles,
        string $correlationId,
    ): User {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());
            [$validatedName, $validatedEmail, $validatedPassword] = $this->validatedIdentity($name, $email, $password);

            return DB::transaction(function () use ($correlationId, $persistedActor, $roles, $tenant, $validatedEmail, $validatedName, $validatedPassword): User {
                $tenantRoles = $this->lockedTenantRoles($roles, (int) $tenant->getKey());
                $roleIds = array_map(static fn (Role $role): int => (int) $role->getKey(), $tenantRoles);
                sort($roleIds);

                try {
                    $user = User::query()->create([
                        'tenant_id' => $tenant->getKey(),
                        'name' => $validatedName,
                        'email' => $validatedEmail,
                        'password' => Hash::make($validatedPassword),
                        'is_active' => true,
                        'lock_version' => 1,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages([
                        'email' => 'The email address has already been taken.',
                    ]);
                }

                $user->syncRoles($tenantRoles);

                $this->auditRecorder->record(
                    eventType: 'tenant.user.created',
                    correlationId: $correlationId,
                    properties: new AuditProperties(['role_ids' => $roleIds]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $user,
                );

                return $user;
            });
        } finally {
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $context->actor->unsetRelation('roles');
            $context->actor->unsetRelation('permissions');
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /** @return array{string, string, string} */
    private function validatedIdentity(string $name, string $email, string $password): array
    {
        $values = [
            'name' => trim($name),
            'email' => trim($email),
            'password' => $password,
        ];

        $values = Validator::make($values, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'email' => ['required', 'string', 'email', 'max:255', 'not_regex:/^\s*$/u', 'unique:users,email'],
            'password' => ['required', 'string', 'not_regex:/^\s*$/u'],
        ])->validate();

        return [$values['name'], $values['email'], $values['password']];
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
        $persistedActor = $this->persistedActor($actor);
        if ($persistedActor === null || ! $this->platformAdministrator->allows($persistedActor, 'platform.users.manage')) {
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
