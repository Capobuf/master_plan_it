<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PlanningYearPolicy;
use App\Support\Authorization\PlatformAdministrator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class ReactivatePlanningYear
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(
        User $actor,
        TenantContext $context,
        PlanningYear $target,
        int $expectedLockVersion,
        string $correlationId,
    ): PlanningYear {
        $this->policy($context)->reactivate($actor, $target)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($context, $correlationId, $expectedLockVersion, $persistedActor, $target, $tenant): PlanningYear {
            $planningYear = $this->lockedPlanningYear($target, (int) $tenant->getKey());

            if ($planningYear->active || $planningYear->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            $updated = PlanningYear::query()
                ->whereKey($planningYear->getKey())
                ->where('tenant_id', $context->tenantId)
                ->where('active', false)
                ->where('lock_version', $expectedLockVersion)
                ->update([
                    'active' => true,
                    'lock_version' => $expectedLockVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new DomainException('STALE_VERSION');
            }

            $planningYear->refresh();
            $this->auditRecorder->record(
                eventType: 'planning-year.reactivated',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'old_active' => false,
                    'new_active' => true,
                    'year_label' => $planningYear->year_label,
                ]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $planningYear,
            );

            return $planningYear;
        });
    }

    private function lockedPlanningYear(PlanningYear $target, int $tenantId): PlanningYear
    {
        $keyName = $target->getKeyName();
        $key = $target->getKey();
        $originalKey = $target->getRawOriginal($keyName);

        if (! $target->exists || $key === null || $key !== $originalKey) {
            throw new DomainException('STALE_VERSION');
        }

        $planningYear = PlanningYear::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($originalKey)
            ->lockForUpdate()
            ->first();

        if ($planningYear === null) {
            throw new DomainException('STALE_VERSION');
        }

        return $planningYear;
    }

    private function activeContextTenant(TenantContext $context): Tenant
    {
        $tenant = Tenant::query()->whereKey($context->tenantId)->first();

        if ($tenant === null) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if ($tenant->state !== TenantState::Active) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }

        return $tenant;
    }

    private function persistedActiveActor(User $actor): User
    {
        $keyName = $actor->getKeyName();
        $key = $actor->getKey();
        $originalKey = $actor->getRawOriginal($keyName);

        if (! $actor->exists || $key === null || $key !== $originalKey) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        $persistedActor = User::query()
            ->whereKey($originalKey)
            ->where('is_active', true)
            ->first();

        if ($persistedActor === null) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $persistedActor;
    }

    private function policy(TenantContext $context): PlanningYearPolicy
    {
        return new PlanningYearPolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }
}
