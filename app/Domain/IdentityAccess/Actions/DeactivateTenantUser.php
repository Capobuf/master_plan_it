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
use Spatie\Permission\PermissionRegistrar;

final class DeactivateTenantUser
{
    public function __construct(
        private readonly TenantAbilityAuthorizer $tenantAbilityAuthorizer,
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
        return $this->tenantAbilityAuthorizer->authorize($actor, $context, 'tenant-users.manage');
    }
}
