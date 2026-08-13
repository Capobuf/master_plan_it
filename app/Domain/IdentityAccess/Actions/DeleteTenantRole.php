<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Services\TenantMutationLock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class DeleteTenantRole
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        Role $target,
        string $correlationId,
    ): void {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            DB::transaction(function () use ($correlationId, $persistedActor, $target, $tenant): void {
                app(TenantMutationLock::class)->shared((int) $tenant->getKey());
                $this->lockAffectedUsers($target, (int) $tenant->getKey());
                $role = $this->lockedTenantRole($target, (int) $tenant->getKey());
                $this->ensureAffectedUsersRetainARole($role, (int) $tenant->getKey());
                $role->delete();

                $this->auditRecorder->record(
                    eventType: 'tenant.role.deleted',
                    correlationId: $correlationId,
                    properties: new AuditProperties([]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $role,
                );
            });
        } finally {
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $context->actor->unsetRelation('roles');
            $context->actor->unsetRelation('permissions');
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function lockAffectedUsers(Role $target, int $tenantId): void
    {
        $roleId = $target->getRawOriginal($target->getKeyName());

        if ($roleId === null) {
            return;
        }

        $userIds = DB::table('model_has_roles')
            ->where('tenant_id', $tenantId)
            ->where('role_id', $roleId)
            ->where('model_type', (new User)->getMorphClass())
            ->orderBy('model_id')
            ->pluck('model_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($userIds !== []) {
            User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }
    }

    private function ensureAffectedUsersRetainARole(Role $role, int $tenantId): void
    {
        $userType = (new User)->getMorphClass();
        $affectedUserIds = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', DB::table('model_has_roles')
                ->select('model_id')
                ->where('tenant_id', $tenantId)
                ->where('role_id', $role->getKey())
                ->where('model_type', $userType))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($affectedUserIds === []) {
            return;
        }

        $usersWithAnotherRole = DB::table('model_has_roles as assignments')
            ->join('roles as assigned_roles', 'assigned_roles.id', '=', 'assignments.role_id')
            ->where('assignments.tenant_id', $tenantId)
            ->where('assigned_roles.tenant_id', $tenantId)
            ->where('assignments.model_type', $userType)
            ->whereIn('assignments.model_id', $affectedUserIds)
            ->where('assignments.role_id', '<>', $role->getKey())
            ->distinct()
            ->pluck('assignments.model_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (array_diff($affectedUserIds, $usersWithAnotherRole) !== []) {
            throw new DomainException('TENANT_ROLE_IN_USE');
        }
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
