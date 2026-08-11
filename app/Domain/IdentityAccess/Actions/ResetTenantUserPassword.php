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
use Illuminate\Support\Facades\Hash;

final class ResetTenantUserPassword
{
    public function __construct(
        private readonly TenantAbilityAuthorizer $tenantAbilityAuthorizer,
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
        return $this->tenantAbilityAuthorizer->authorize($actor, $context, 'tenant-users.manage');
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
