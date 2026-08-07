<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Reporting\Queries\TenantDashboardQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportingDatasetResource;
use App\Models\PlanningYear;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        EconomicDatasetQuery $datasetQuery,
        EconomicEngine $engine,
        TenantDashboardQuery $dashboard,
    ): ReportingDatasetResource {
        $context = $this->tenantContext($request);
        $years = PlanningYear::query()
            ->where('tenant_id', $context->tenantId)
            ->orderBy('year_label')
            ->orderBy('id')
            ->get(['id', 'year_label', 'active']);
        $selected = $this->selectedYear($request, $years, $context->timezone);

        if (! $selected instanceof PlanningYear) {
            return ReportingDatasetResource::make([
                'dataset' => null,
                'calculated' => null,
                'cost_center_id' => null,
                'dashboard' => true,
                'year_options' => [],
                'selected_year_id' => null,
                'ancillary' => $this->emptyAncillary(),
            ]);
        }

        $planningYearId = (int) $selected->getKey();
        $dataset = $datasetQuery->execute($this->actor($request), $context, $planningYearId);

        return ReportingDatasetResource::make([
            'dataset' => $dataset,
            'calculated' => $engine->calculate($dataset),
            'cost_center_id' => null,
            'dashboard' => true,
            'year_options' => $years->map(static fn (PlanningYear $year): array => [
                'id' => (int) $year->getKey(),
                'label' => (int) $year->year_label,
                'active' => (bool) $year->active,
            ])->values()->all(),
            'selected_year_id' => $planningYearId,
            'ancillary' => $dashboard->apiDataset($context, $planningYearId),
        ]);
    }

    /** @param Collection<int, PlanningYear> $years */
    private function selectedYear(Request $request, Collection $years, string $timezone): ?PlanningYear
    {
        $value = $request->query('planning_year_id', $request->query('year'));

        if ($value !== null && (! is_numeric($value) || (int) $value < 1)) {
            abort(404);
        }

        if ($value !== null) {
            $selected = $years->firstWhere('id', (int) $value);
            if (! $selected instanceof PlanningYear) {
                abort(404);
            }

            return $selected;
        }

        $currentYear = (int) now($timezone)->year;

        return $years->first(fn (PlanningYear $year): bool => $year->active && (int) $year->year_label === $currentYear)
            ?? $years->firstWhere('active', true)
            ?? $years->sortByDesc('year_label')->first();
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function emptyAncillary(): array
    {
        return [
            'recentExpenses' => [],
            'generatedExpensesToConfirm' => [],
            'activeContracts' => [],
            'upcomingContractEvents' => [],
        ];
    }
}
