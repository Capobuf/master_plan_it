<?php

namespace App\Domain\MasterData\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PlanningYearPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class CreatePlanningYear
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(
        User $actor,
        TenantContext $context,
        CreatePlanningYearData $data,
        string $correlationId,
    ): PlanningYear {
        $this->policy($context)->create($actor)->authorize();
        $tenant = $this->activeContextTenant($context);
        $persistedActor = $this->persistedActiveActor($actor);

        return DB::transaction(function () use ($context, $correlationId, $data, $persistedActor, $tenant): PlanningYear {
            try {
                $planningYear = PlanningYear::query()->create([
                    'tenant_id' => $tenant->getKey(),
                    'year_label' => $data->yearLabel,
                    'active' => true,
                    'lock_version' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'year_label' => 'The planning year already exists for the current tenant.',
                ]);
            }

            $this->auditRecorder->record(
                eventType: 'planning-year.created',
                correlationId: $correlationId,
                properties: new AuditProperties([
                    'year_label' => $planningYear->year_label,
                    'active' => true,
                    'start_date' => $data->startDate(),
                    'end_date' => $data->endDate(),
                ]),
                actor: $persistedActor,
                tenantId: $context->tenantId,
                subject: $planningYear,
            );

            return $planningYear;
        });
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
