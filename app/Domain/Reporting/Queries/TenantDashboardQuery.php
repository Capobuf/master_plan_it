<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class TenantDashboardQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
    ) {}

    /** @return array<string, mixed> */
    public function apiDataset(User $actor, TenantContext $context, int $planningYearId): array
    {
        $projection = $this->engine->project(
            $this->datasetQuery->execute($actor, $context, $planningYearId),
        );
        $metadata = DB::table('expenses')
            ->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')->on('projects.tenant_id', '=', 'expenses.tenant_id')->whereNull('projects.deleted_at'))
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expenses.deleted_at')
            ->get([
                'expenses.id', 'expenses.title', 'expenses.updated_at', 'expenses.cost_center_id',
                'cost_centers.name as cost_center_name', 'expenses.project_id', 'projects.title as project_title',
            ])->keyBy('id');

        $recent = collect($projection->expenses)
            ->map(function ($expense) use ($metadata, $projection): ?array {
                $record = $metadata->get($expense->expenseId);
                if ($record === null) {
                    return null;
                }
                $vendors = collect($expense->lines)->pluck('vendorName')->filter()->unique()->sort()->values()->all();

                return [
                    'expense_id' => $expense->expenseId,
                    'title' => (string) $record->title,
                    'updated_at' => (string) $record->updated_at,
                    'cost_center_name' => (string) $record->cost_center_name,
                    'project_title' => $record->project_title === null ? null : (string) $record->project_title,
                    'vendor_summary' => $vendors === [] ? null : implode(', ', $vendors),
                    'currency' => $projection->currency,
                    'basis' => $projection->basis,
                    'totals' => $this->totals($expense->currentPlanning, $expense->actual),
                ];
            })
            ->filter()
            ->sortByDesc('updated_at')
            ->take(8)
            ->values()
            ->all();

        $costCenterLabels = $metadata->mapWithKeys(static fn (object $row): array => [(int) $row->cost_center_id => (string) $row->cost_center_name])->all();
        $projectLabels = $metadata->filter(static fn (object $row): bool => $row->project_id !== null)
            ->mapWithKeys(static fn (object $row): array => [(int) $row->project_id => (string) $row->project_title])->all();

        return [
            'planning_year_id' => $projection->planningYearId,
            'economic_year_label' => $projection->economicYearLabel,
            'currency' => $projection->currency,
            'basis' => $projection->basis,
            'totals' => $this->totals($projection->currentPlanning, $projection->actual),
            'has_economic_data' => $projection->lines !== [],
            'expense_count' => count($projection->expenses),
            'recent_expenses' => $recent,
            'monthly' => $this->buckets($projection, 'month'),
            'by_type' => $this->buckets($projection, 'type'),
            'by_cost_center' => $this->buckets($projection, 'cost_center', $costCenterLabels),
            'by_project' => $this->buckets($projection, 'project', $projectLabels),
            ...$this->contractAncillary($context),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @return list<array<string, mixed>>
     */
    private function buckets(AnnualEconomicProjection $projection, string $dimension, array $labels = []): array
    {
        /** @var array<string, array{label:string,current:EconomicMeasure,actual:EconomicMeasure}> $groups */
        $groups = [];
        foreach ($projection->lines as $line) {
            [$key, $label] = $this->bucketIdentity($line, $dimension, $labels);
            if ($key === null) {
                continue;
            }
            $groups[$key] ??= [
                'label' => $label,
                'current' => EconomicMeasure::zero($projection->basis),
                'actual' => EconomicMeasure::zero($projection->basis),
            ];
            if ($line->contributesToCurrentPlanning) {
                $groups[$key]['current'] = $groups[$key]['current']->plus($line->amount, $projection->basis);
            }
            if ($line->type === 'actual') {
                $groups[$key]['actual'] = $groups[$key]['actual']->plus($line->amount, $projection->basis);
            }
        }
        ksort($groups);

        return array_map(fn (string $key, array $group): array => [
            'key' => $key,
            'label' => $group['label'],
            'currency' => $projection->currency,
            'basis' => $projection->basis,
            'totals' => $this->totals($group['current'], $group['actual']),
        ], array_keys($groups), array_values($groups));
    }

    /**
     * @param  array<int, string>  $labels
     * @return array{0: ?string, 1: string}
     */
    private function bucketIdentity(ProjectedEconomicLine $line, string $dimension, array $labels): array
    {
        if ($dimension === 'month') {
            if ($line->spendDate === null) {
                return [null, ''];
            }
            $month = substr($line->spendDate, 0, 7);
            $label = CarbonImmutable::createFromFormat('!Y-m', $month, 'UTC')->locale('it')->translatedFormat('F Y');

            return [$month, ucfirst($label)];
        }
        if ($dimension === 'type') {
            return [$line->type, match ($line->type) {
                'estimate' => 'Stima',
                'quote' => 'Preventivo',
                'actual' => 'Effettivo',
                default => ucfirst($line->type),
            }];
        }
        if ($dimension === 'cost_center') {
            return ['cost-center:'.$line->costCenterId, $labels[$line->costCenterId] ?? '—'];
        }
        if ($dimension === 'project') {
            return [$line->projectId === null ? 'project:none' : 'project:'.$line->projectId, $line->projectId === null ? 'Senza progetto' : ($labels[$line->projectId] ?? '—')];
        }

        throw new \LogicException('Unsupported dashboard projection dimension.');
    }

    /** @return array{current_planning: array<string, string>, actual: array<string, string>} */
    private function totals(EconomicMeasure $current, EconomicMeasure $actual): array
    {
        return ['current_planning' => $this->measure($current), 'actual' => $this->measure($actual)];
    }

    /** @return array{net:string,vat:string,gross:string,official:string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }

    /** @return array<string, mixed> */
    private function contractAncillary(TenantContext $context): array
    {
        $generated = DB::table('expense_rows')
            ->join('expenses', 'expenses.id', '=', 'expense_rows.expense_id')
            ->where('expense_rows.tenant_id', $context->tenantId)
            ->whereNull('expense_rows.deleted_at')->whereNull('expenses.deleted_at')->whereNotNull('expense_rows.source_key')
            ->orderByDesc('expense_rows.updated_at')->limit(8)
            ->get(['expenses.id', 'expenses.title', 'expense_rows.updated_at', 'expense_rows.manual_override_at'])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id, 'label' => (string) $row->title,
                'state' => $row->manual_override_at === null ? 'managed' : 'manual', 'date' => (string) $row->updated_at,
            ])->all();
        $active = DB::table('contracts')->where('tenant_id', $context->tenantId)->whereNull('deleted_at')->where('active', true)
            ->orderBy('title')->limit(8)->get(['id', 'title'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'label' => (string) $row->title, 'state' => 'active'])->all();
        $today = now($context->timezone)->toDateString();
        $limit = now($context->timezone)->addYear()->toDateString();
        $renewals = DB::table('contracts')->where('tenant_id', $context->tenantId)->whereNull('deleted_at')->where('active', true)
            ->whereBetween('renewal_date', [$today, $limit])->get(['id', 'title', 'renewal_date as event_date'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'label' => (string) $row->title, 'event_type' => 'renewal', 'date' => (string) $row->event_date]);
        $ends = DB::table('contract_terms')
            ->join('contracts', fn ($join) => $join->on('contracts.id', '=', 'contract_terms.contract_id')->on('contracts.tenant_id', '=', 'contract_terms.tenant_id'))
            ->where('contract_terms.tenant_id', $context->tenantId)->whereNull('contract_terms.deleted_at')->whereNull('contracts.deleted_at')
            ->where('contracts.active', true)->whereBetween('contract_terms.effective_end', [$today, $limit])
            ->get(['contracts.id', 'contracts.title', 'contract_terms.effective_end as event_date'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'label' => (string) $row->title, 'event_type' => 'contract_end', 'date' => (string) $row->event_date]);

        return [
            'generated_contract_planning' => $generated,
            'active_contracts' => $active,
            'upcoming_contract_events' => $renewals->concat($ends)->sortBy('date')->take(8)->values()->all(),
        ];
    }
}
