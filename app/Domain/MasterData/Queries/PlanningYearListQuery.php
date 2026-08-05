<?php

namespace App\Domain\MasterData\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use App\Policies\PlanningYearPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

final class PlanningYearListQuery
{
    /** @return Builder<PlanningYear> */
    public function forTenant(User $actor, TenantContext $context): Builder
    {
        $this->policy($context)->viewAny($actor)->authorize();

        return TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->orderBy('year_label');
    }

    /** @return Builder<PlanningYear> */
    public function forNewSelection(
        User $actor,
        TenantContext $context,
        ?int $currentPlanningYearId = null,
    ): Builder
    {
        return $this->forTenant($actor, $context)
            ->where(function (Builder $query) use ($currentPlanningYearId): void {
                $query->where('active', true);

                if ($currentPlanningYearId !== null) {
                    $query->orWhere(function (Builder $current) use ($currentPlanningYearId): void {
                        $current
                            ->whereKey($currentPlanningYearId)
                            ->where('active', false);
                    });
                }
            });
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
