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
use Illuminate\Support\Facades\Hash;

final class ResetTenantUserPassword
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly InvalidateUserSessions $invalidateUserSessions,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        User $target,
        string $secret,
        string $correlationId,
    ): User {
        [$persistedActor, $tenant] = $this->authorize($actor, $context);
        $persistedTarget = $this->sameTenantTarget($target, (int) $tenant->getKey());

        return DB::transaction(function () use ($correlationId, $persistedActor, $persistedTarget, $secret, $tenant): User {
            $persistedTarget->forceFill(['password' => Hash::make($secret)])->save();

            $this->invalidateUserSessions->execute($persistedTarget);

            $this->auditRecorder->record(
                eventType: 'tenant.user.password-reset',
                correlationId: $correlationId,
                properties: new AuditProperties([]),
                actor: $persistedActor,
                tenantId: (int) $tenant->getKey(),
                subject: $persistedTarget,
            );

            return $persistedTarget;
        });
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

    private function sameTenantTarget(User $target, int $tenantId): User
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);

        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        $persistedTarget = User::query()->whereKey($originalKey)->where('tenant_id', $tenantId)->first();

        if ($persistedTarget === null) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }

        return $persistedTarget;
    }
}
