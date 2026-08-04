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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class DeactivateTenantUser
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  list<int>  $openAssignmentIds
     * @return list<int>
     */
    public function execute(
        User $actor,
        TenantContext $context,
        User $target,
        array $openAssignmentIds,
        string $correlationId,
    ): array {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            [$persistedActor, $tenant] = $this->authorize($actor, $context);
            $this->permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());
            $reassignmentNeededIds = $this->normalizedAssignmentIds($openAssignmentIds);

            return DB::transaction(function () use ($correlationId, $persistedActor, $reassignmentNeededIds, $target, $tenant): array {
                $user = $this->lockedTenantUser($target, (int) $tenant->getKey());
                $user->forceFill([
                    'is_active' => false,
                    'lock_version' => $user->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(
                    eventType: 'tenant.user.deactivated',
                    correlationId: $correlationId,
                    properties: new AuditProperties(['reassignment_needed_ids' => $reassignmentNeededIds]),
                    actor: $persistedActor,
                    tenantId: (int) $tenant->getKey(),
                    subject: $user,
                );

                return $reassignmentNeededIds;
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

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function normalizedAssignmentIds(array $ids): array
    {
        foreach ($ids as $index => $id) {
            if (! is_int($id)) {
                throw ValidationException::withMessages([
                    "open_assignment_ids.{$index}" => 'Open assignment identifiers must be integers.',
                ]);
            }
        }

        $normalized = array_values(array_unique($ids, SORT_NUMERIC));
        sort($normalized, SORT_NUMERIC);

        return $normalized;
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
