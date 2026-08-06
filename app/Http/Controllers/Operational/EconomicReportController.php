<?php

namespace App\Http\Controllers\Operational;

use App\Domain\Reporting\Data\EconomicReportData;
use App\Domain\Reporting\Queries\EconomicReportQuery;
use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Support\Formatting\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;

final class EconomicReportController extends Controller
{
    public function __invoke(Request $request, EconomicReportQuery $report): View
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $years = PlanningYear::query()->where('tenant_id', $context->tenantId)->orderBy('year_label')->orderBy('id')->get(['id', 'year_label', 'active']);
        $selectedYear = $this->selectedYear($request, $years, $context->timezone);
        $selectedCostCenterId = $this->selectedCostCenterId($request, $context->tenantId);
        $props = [
            'yearOptions' => $years->map(static fn (PlanningYear $year): array => ['value' => (int) $year->getKey(), 'label' => (string) $year->year_label, 'active' => (bool) $year->active])->values()->all(),
            'costCenterOptions' => CostCenter::query()->where('tenant_id', $context->tenantId)->orderByRaw('LOWER(name)')->orderBy('id')->get(['id', 'name', 'active'])->map(static fn (CostCenter $costCenter): array => ['value' => (int) $costCenter->getKey(), 'label' => (string) $costCenter->name.($costCenter->active ? '' : ' · Inactive')])->all(),
            'selectedYear' => $selectedYear?->getKey(),
            'selectedCostCenter' => $selectedCostCenterId,
            'summary' => null,
            'lines' => ['data' => [], 'currentPage' => 1, 'lastPage' => 1, 'perPage' => 25, 'total' => 0, 'from' => null, 'to' => null, 'links' => []],
            'hasEconomicData' => false,
        ];

        if (! $selectedYear instanceof PlanningYear) {
            return view('operational.reports.index', $props);
        }

        $result = $report->execute($actor, $context, (int) $selectedYear->getKey(), $selectedCostCenterId, max(1, (int) $request->query('page', 1)));
        $props['summary'] = [
            'officialBasis' => $result->calculated['summary']->officialBasis,
            ...array_map(fn (string $amount): string => MoneyFormatter::format($amount, $context->currencyCode), $result->calculated['summary']->amounts),
        ];
        $props['lines'] = $this->lines($result, $context->currencyCode, $request);
        $props['hasEconomicData'] = $result->total > 0;

        return view('operational.reports.index', $props);
    }

    /** @param Collection<int, PlanningYear> $years */
    private function selectedYear(Request $request, Collection $years, string $timezone): ?PlanningYear
    {
        $requested = $request->query('year');
        $selected = is_numeric($requested) ? $years->firstWhere('id', (int) $requested) : null;
        if ($requested !== null && ! $selected instanceof PlanningYear) abort(404);
        if ($selected instanceof PlanningYear) return $selected;
        $currentYear = (int) now($timezone)->year;

        return $years->first(fn (PlanningYear $year): bool => $year->active && (int) $year->year_label === $currentYear)
            ?? $years->firstWhere('active', true)
            ?? $years->sortByDesc('year_label')->first();
    }

    private function selectedCostCenterId(Request $request, int $tenantId): ?int
    {
        $requested = $request->query('cost_center');
        if ($requested === null || $requested === '') return null;
        if (! is_numeric($requested) || (int) $requested < 1) abort(404);
        if (! CostCenter::query()->where('tenant_id', $tenantId)->whereKey((int) $requested)->exists()) abort(404);

        return (int) $requested;
    }

    /** @return array<string, mixed> */
    private function lines(EconomicReportData $report, string $currency, Request $request): array
    {
        $from = $report->total === 0 ? null : (($report->page - 1) * $report->perPage) + 1;
        $to = $report->total === 0 ? null : min($report->page * $report->perPage, $report->total);
        $query = $request->query();
        $links = [];
        for ($page = 1; $page <= $report->lastPage(); $page++) {
            $links[] = ['url' => $page === $report->page ? null : route('operational.reports.index', [...$query, 'page' => $page]), 'label' => (string) $page, 'active' => $page === $report->page];
        }

        return [
            'data' => array_map(fn ($line): array => [
                'id' => $line->rowId,
                'expenseId' => $line->expenseId,
                'costCenterName' => $line->costCenterName,
                'type' => $line->type,
                'confirmationState' => $line->confirmationState,
                'net' => MoneyFormatter::format($line->net, $currency),
                'vat' => MoneyFormatter::format($line->vat, $currency),
                'gross' => MoneyFormatter::format($line->gross, $currency),
            ], $report->lines),
            'currentPage' => $report->page, 'lastPage' => $report->lastPage(), 'perPage' => $report->perPage,
            'total' => $report->total, 'from' => $from, 'to' => $to, 'links' => $links,
        ];
    }
}
