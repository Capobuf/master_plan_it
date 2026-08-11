<?php

namespace App\Support\Authorization;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class TenantAbilityAuthorizer
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /** @return array{User, Tenant} */
    public function authorize(User $actor, TenantContext $context, string $ability): array
    {
        $persistedActor = $this->persistedActor($actor);
        $tenant = $this->persistedTenant($context);

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if (! $this->contextMatches($persistedActor, $context)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        if ($persistedActor->tenant_id === null) {
            $allowed = false;
            foreach (PermissionCatalogue::authorizationCandidates($ability) as $candidate) {
                $allowed = $allowed || $this->platformAdministrator->allows($persistedActor, $candidate);
            }
            if (! $this->platformAdministrator->hasProtectedRole($persistedActor) || ! $allowed) {
                throw new AuthorizationException('PERMISSION_DENIED');
            }

            return [$persistedActor, $tenant];
        }

        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }

        if ((int) $persistedActor->tenant_id !== (int) $tenant->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $previousTeamId = $this->registrar->getPermissionsTeamId();
        $persistedActor->unsetRelation('roles');
        $persistedActor->unsetRelation('permissions');
        $this->registrar->setPermissionsTeamId((int) $tenant->getKey());

        try {
            $allowed = false;
            foreach (PermissionCatalogue::authorizationCandidates($ability) as $candidate) {
                $allowed = $allowed || $persistedActor->checkPermissionTo($candidate, 'web');
            }
            if (! $allowed) {
                throw new AuthorizationException('PERMISSION_DENIED');
            }
        } catch (PermissionDoesNotExist) {
            throw new AuthorizationException('PERMISSION_DENIED');
        } finally {
            $persistedActor->unsetRelation('roles');
            $persistedActor->unsetRelation('permissions');
            $this->registrar->setPermissionsTeamId($previousTeamId);
        }

        return [$persistedActor, $tenant];
    }

    public function allows(User $actor, TenantContext $context, string $ability): bool
    {
        try {
            $this->authorize($actor, $context, $ability);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function persistedActor(User $actor): ?User
    {
        $key = $actor->getKey();
        $original = $actor->getRawOriginal($actor->getKeyName());

        return $actor->exists && $key !== null && $key === $original
            ? User::query()->whereKey($original)->where('is_active', true)->first()
            : null;
    }

    private function persistedTenant(TenantContext $context): ?Tenant
    {
        $key = $context->tenant->getKey();
        $original = $context->tenant->getRawOriginal($context->tenant->getKeyName());

        return $context->tenant->exists && $key !== null && $key === $original && (int) $key === $context->tenantId
            ? Tenant::query()->whereKey($original)->first()
            : null;
    }

    private function contextMatches(User $actor, TenantContext $context): bool
    {
        $contextActorKey = $context->actor->getKey();

        return $context->actor->exists
            && $contextActorKey !== null
            && $contextActorKey === $context->actor->getRawOriginal($context->actor->getKeyName())
            && $contextActorKey === $actor->getKey();
    }
}
