<?php

namespace App\Domain\Plafonds\Queries;

use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use App\Domain\Plafonds\Services\PlafondReadAuthorizer;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\User;
use DomainException;

final class PlafondReportQuery
{
    /** @return array{data: list<array<string, mixed>>, currency: string, basis: string, filters: array{planning_year_id: int, cost_center_id: ?int}} */
    public function execute(
        User $actor,
        TenantContext $context,
        int $planningYearId,
        ?int $costCenterId,
    ): array {
        app(PlafondReadAuthorizer::class)->authorize($actor, $context);
        TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $planningYearId);
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
        $rootIds = $roots->modelKeys();
        $rowMetadata = ExpenseRow::query()
            ->where('tenant_id', $context->tenantId)
            ->whereIn('expense_id', $rootIds)
            ->get(['id', 'position', 'lock_version'])
            ->keyBy('id');
        $data = $roots->map(function (Expense $expense) use ($annual, $rowMetadata): array {
            $projection = $annual->plafonds[(int) $expense->getKey()] ?? null;
            if ($projection === null) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $allocationLines = array_map(function (ProjectedEconomicLine $line) use ($rowMetadata): array {
                $metadata = $rowMetadata->get($line->rowId);
                if ($metadata === null) {
                    throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
                }

                return PlafondProjectionSerializer::allocationLine(
                    $line,
                    (int) $metadata->position,
                    (int) $metadata->lock_version,
                );
            }, $projection->allocationLines);

            return [
                'plafond' => [
                    'id' => (int) $expense->getKey(),
                    'title' => (string) $expense->title,
                    'cost_center' => [
                        'id' => (int) $expense->cost_center_id,
                        'name' => (string) $expense->costCenter?->name,
                    ],
                ],
                'currency' => $annual->currency,
                'basis' => $annual->basis,
                'measures' => PlafondProjectionSerializer::measures($projection),
                'allocation_lines' => $allocationLines,
                'covered_lines' => array_map(
                    static fn (ProjectedEconomicLine $line): array => PlafondProjectionSerializer::coveredLine($line),
                    $projection->coveredLines,
                ),
            ];
        })->values()->all();

        return [
            'data' => $data,
            'currency' => $annual->currency,
            'basis' => $annual->basis,
            'filters' => [
                'planning_year_id' => $planningYearId,
                'cost_center_id' => $costCenterId,
            ],
        ];
    }
}
