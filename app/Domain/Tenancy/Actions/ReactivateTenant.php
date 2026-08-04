<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ReactivateTenant
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        Tenant $target,
        int $expectedLockVersion,
        string $correlationId,
    ): Tenant {
        if (! $this->platformAdministrator->allows($actor, 'platform.tenants.reactivate')) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return DB::transaction(function () use ($actor, $correlationId, $expectedLockVersion, $target): Tenant {
            $persistedActor = $this->persistedActiveTenantlessActor($actor);
            $tenant = $this->lockedTenant($target);

            if ($tenant->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            if ($tenant->state !== TenantState::Inactive) {
                throw new DomainException('STALE_VERSION');
            }

            $changedAt = CarbonImmutable::now('UTC');
            $updated = Tenant::query()
                ->whereKey($tenant->getKey())
                ->where('state', TenantState::Inactive->value)
                ->where('lock_version', $expectedLockVersion)
                ->update([
                    'state' => TenantState::Active->value,
                    'state_changed_by_user_id' => $persistedActor->getKey(),
                    'state_changed_at' => $changedAt,
                    'lock_version' => $expectedLockVersion + 1,
                    'updated_at' => $changedAt,
                ]);

            if ($updated !== 1) {
                throw new DomainException('STALE_VERSION');
            }

            $tenant->refresh();

            $this->auditRecorder->record(
                eventType: 'tenant.reactivated',
                correlationId: $correlationId,
                properties: new AuditProperties([]),
                actor: $persistedActor,
                tenantId: (int) $tenant->getKey(),
                subject: $tenant,
                occurredAt: $changedAt,
            );

            return $tenant;
        });
    }

    private function lockedTenant(Tenant $target): Tenant
    {
        if (! $target->exists || $target->getKey() === null) {
            throw new DomainException('STALE_VERSION');
        }

        $tenant = Tenant::query()->lockForUpdate()->find($target->getKey());

        if ($tenant === null) {
            throw new DomainException('STALE_VERSION');
        }

        return $tenant;
    }

    private function persistedActiveTenantlessActor(User $actor): User
    {
        $keyName = $actor->getKeyName();
        $currentKey = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);

        if (
            ! $actor->exists
            || (! is_int($currentKey) && ! is_string($currentKey))
            || (! is_int($originalKey) && ! is_string($originalKey))
            || $currentKey !== $originalKey
        ) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $persistedActor = User::query()
            ->whereKey($originalKey)
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->first();

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $persistedActor;
    }
}
