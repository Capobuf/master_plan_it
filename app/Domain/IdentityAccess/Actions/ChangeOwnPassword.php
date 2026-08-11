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

final class ChangeOwnPassword
{
    public function __construct(
        private readonly InvalidateUserSessions $invalidateUserSessions,
        private readonly AuditRecorder $auditRecorder,
        private readonly PlatformAdministrator $platformAdministrator,
    ) {}

    public function execute(
        User $actor,
        ?TenantContext $context,
        string $currentPassword,
        string $newPassword,
        string $correlationId,
    ): User {
        [$persistedActor, $tenant] = $this->authorize($actor, $context);

        if (! Hash::check($currentPassword, (string) $persistedActor->password)) {
            throw new DomainException('CURRENT_PASSWORD_INVALID');
        }

        return DB::transaction(function () use ($correlationId, $persistedActor, $newPassword, $tenant): User {
            $persistedActor->forceFill(['password' => Hash::make($newPassword)])->save();

            $this->invalidateUserSessions->execute($persistedActor);

            $this->auditRecorder->record(
                eventType: 'user.password.changed',
                correlationId: $correlationId,
                properties: new AuditProperties([]),
                actor: $persistedActor,
                tenantId: $tenant === null ? null : (int) $tenant->getKey(),
                subject: $persistedActor,
            );

            return $persistedActor;
        });
    }

    /** @return array{User, ?Tenant} */
    private function authorize(User $actor, ?TenantContext $context): array
    {
        $persistedActor = $this->persistedActor($actor);

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        if ($context === null) {
            if (
                $persistedActor->tenant_id !== null
                || ! $this->platformAdministrator->hasProtectedRole($persistedActor)
            ) {
                throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
            }

            return [$persistedActor, null];
        }

        if ($this->persistedActor($context->actor)?->getKey() !== $persistedActor->getKey()) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $tenant = $this->persistedContextTenant($context);

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if (
            $persistedActor->tenant_id === null
                ? ! $this->platformAdministrator->hasProtectedRole($persistedActor)
                : (int) $persistedActor->tenant_id !== (int) $tenant->getKey()
        ) {
            throw new AuthorizationException('PERMISSION_DENIED');
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

        return User::query()->whereKey($originalKey)->where('is_active', true)->first();
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
