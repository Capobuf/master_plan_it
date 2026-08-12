<?php

namespace App\Domain\Plafonds\Queries;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use App\Domain\Plafonds\Services\PlafondReadAuthorizer;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\User;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;

final class PlafondListQuery
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(
        User $actor,
        TenantContext $context,
        int $planningYearId,
        ?int $costCenterId,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        app(PlafondReadAuthorizer::class)->authorize($actor, $context);
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->whereKey($planningYearId)
            ->firstOrFail();
        if ($costCenterId !== null) {
            TenantOwnedRecordQuery::findOrFail($context, CostCenter::class, $costCenterId);
        }
        $annual = app(EconomicEngine::class)->project(
            app(EconomicDatasetQuery::class)->execute($actor, $context, $planningYearId),
        );
        $roots = TenantOwnedRecordQuery::forTenant($context, Expense::class)
            ->where('planning_year_id', $planningYearId)
            ->where('kind', ExpenseKind::Plafond->value)
            ->when($costCenterId !== null, fn ($query) => $query->where('cost_center_id', $costCenterId))
            ->with('costCenter:id,tenant_id,name')
            ->orderBy('id')
            ->get();
        $items = $roots->map(function (Expense $expense) use ($annual, $year): array {
            $projection = $annual->plafonds[(int) $expense->getKey()] ?? null;
            if ($projection === null) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }

            return [
                'id' => (int) $expense->getKey(),
                'planning_year_id' => (int) $expense->planning_year_id,
                'economic_year_label' => (int) $year->year_label,
                'title' => (string) $expense->title,
                'notes' => $expense->notes,
                'cost_center' => [
                    'id' => (int) $expense->cost_center_id,
                    'name' => (string) $expense->costCenter?->name,
                ],
                'lock_version' => (int) $expense->lock_version,
                'currency' => $annual->currency,
                'basis' => $annual->basis,
                'measures' => PlafondProjectionSerializer::measures($projection),
            ];
        })->values();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
        );

        return $paginator;
    }
}
