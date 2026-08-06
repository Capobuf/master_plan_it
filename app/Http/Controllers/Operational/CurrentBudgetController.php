<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Reporting\Queries\CurrentBudgetQuery;
use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Support\Formatting\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;

final class CurrentBudgetController extends Controller
{
    public function __invoke(Request $request, CurrentBudgetQuery $budget): View
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $years = PlanningYear::query()
            ->where('tenant_id', $context->tenantId)
            ->orderBy('year_label')
            ->orderBy('id')
            ->get(['id', 'year_label', 'active']);

        $selectedYear = $this->selectedYear($request, $years, $context->timezone);
        $selectedCostCenterId = $this->selectedCostCenterId($request, $context->tenantId);
        $costCenters = CostCenter::query()
            ->where('tenant_id', $context->tenantId)
            ->orderByRaw('LOWER(name)')
            ->orderBy('id')
            ->get(['id', 'name', 'active'])
            ->map(static fn (CostCenter $costCenter): array => [
                'value' => (int) $costCenter->getKey(),
                'label' => (string) $costCenter->name.($costCenter->active ? '' : ' · Inactive'),
            ])->all();

        $props = [
            'yearOptions' => $years->map(static fn (PlanningYear $year): array => [
                'value' => (int) $year->getKey(),
                'label' => (string) $year->year_label,
                'active' => (bool) $year->active,
            ])->values()->all(),
            'costCenterOptions' => $costCenters,
            'selectedYear' => $selectedYear?->getKey(),
            'selectedCostCenter' => $selectedCostCenterId,
            'summary' => null,
            'chart' => ['labels' => [], 'datasets' => []],
            'breakdown' => [],
            'hasEconomicData' => false,
        ];

        if (! $selectedYear instanceof PlanningYear) {
            return view('operational.budget.index', $props);
        }

        $result = $budget->execute($actor, $context, (int) $selectedYear->getKey(), $selectedCostCenterId);
        $amounts = $result->calculated['summary']->amounts;
        $byType = $result->calculated['byType'];

        $props['summary'] = [
            'officialBasis' => $result->calculated['summary']->officialBasis,
            ...array_map(fn (string $amount): string => MoneyFormatter::format($amount, $context->currencyCode), $amounts),
        ];
        $props['chart'] = [
            'labels' => array_keys($byType),
            'datasets' => [[
                'label' => 'Current Budget by component',
                'data' => array_map(static fn (string $amount): float => (float) $amount, array_values($byType)),
            ]],
        ];
        $props['breakdown'] = array_map(
            fn (string $amount, string $type): array => [
                'label' => ucfirst($type),
                'value' => MoneyFormatter::format($amount, $context->currencyCode),
            ],
            $byType,
            array_keys($byType),
        );
        $props['hasEconomicData'] = $result->dataset->lines !== [];

        return view('operational.budget.index', $props);
    }

    /** @param Collection<int, PlanningYear> $years */
    private function selectedYear(Request $request, $years, string $timezone): ?PlanningYear
    {
        $requested = $request->query('year');
        $selected = is_numeric($requested) ? $years->firstWhere('id', (int) $requested) : null;

        if ($requested !== null && ! $selected instanceof PlanningYear) {
            abort(404);
        }

        if ($selected instanceof PlanningYear) {
            return $selected;
        }

        $currentYear = (int) now($timezone)->year;

        return $years->first(fn (PlanningYear $year): bool => $year->active && (int) $year->year_label === $currentYear)
            ?? $years->firstWhere('active', true)
            ?? $years->sortByDesc('year_label')->first();
    }

    private function selectedCostCenterId(Request $request, int $tenantId): ?int
    {
        $requested = $request->query('cost_center');
        if ($requested === null || $requested === '') {
            return null;
        }
        if (! is_numeric($requested) || (int) $requested < 1) {
            abort(404);
        }

        $costCenter = CostCenter::query()
            ->where('tenant_id', $tenantId)
            ->whereKey((int) $requested)
            ->first();
        if (! $costCenter instanceof CostCenter) {
            abort(404);
        }

        return (int) $costCenter->getKey();
    }
}
