<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use Illuminate\Support\Facades\DB;

final class TenantDashboardQuery
{
    /**
     * Presentation-neutral dashboard attention data. No URLs, route names or
     * localized labels cross the API/domain boundary.
     *
     * @return array<string, mixed>
     */
    public function apiDataset(TenantContext $context, int $planningYearId): array
    {
        $officialAmountColumn = $context->budgetBasis->value === 'gross' ? 'gross_amount' : 'net_amount';
        $actualTotals = DB::table('expense_rows')
            ->select(['tenant_id', 'expense_id'])
            ->selectRaw("SUM({$officialAmountColumn}) as actual")
            ->where('tenant_id', $context->tenantId)
            ->where('type', 'actual')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id', 'expense_id');
        $recent = DB::table('expenses')
            ->join('cost_centers', fn ($join) => $join
                ->on('cost_centers.id', '=', 'expenses.cost_center_id')
                ->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join
                ->on('projects.id', '=', 'expenses.project_id')
                ->on('projects.tenant_id', '=', 'expenses.tenant_id')
                ->whereNull('projects.deleted_at'))
            ->leftJoin('expense_rows as current_planning_rows', fn ($join) => $join
                ->on('current_planning_rows.id', '=', 'expenses.current_planning_row_id')
                ->on('current_planning_rows.expense_id', '=', 'expenses.id')
                ->on('current_planning_rows.tenant_id', '=', 'expenses.tenant_id')
                ->whereNull('current_planning_rows.deleted_at'))
            ->leftJoin('vendors', fn ($join) => $join
                ->on('vendors.id', '=', 'current_planning_rows.vendor_id')
                ->on('vendors.tenant_id', '=', 'expenses.tenant_id')
                ->whereNull('vendors.deleted_at'))
            ->leftJoinSub($actualTotals, 'actual_totals', fn ($join) => $join
                ->on('actual_totals.expense_id', '=', 'expenses.id')
                ->on('actual_totals.tenant_id', '=', 'expenses.tenant_id'))
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expenses.deleted_at')
            ->orderByDesc('expenses.updated_at')
            ->orderByDesc('expenses.id')
            ->limit(8)
            ->get([
                'expenses.id', 'expenses.title', 'expenses.updated_at', 'expenses.state',
                'cost_centers.name as cost_center', 'projects.title as project', 'vendors.name as vendor',
                DB::raw("COALESCE(current_planning_rows.{$officialAmountColumn}, 0) as planned"),
                DB::raw('COALESCE(actual_totals.actual, 0) as actual'),
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'date' => (string) $row->updated_at,
                'cost_center' => (string) $row->cost_center,
                'project' => $row->project === null ? null : (string) $row->project,
                'vendor' => $row->vendor === null ? null : (string) $row->vendor,
                'planned' => bcadd((string) $row->planned, '0', 2),
                'actual' => bcadd((string) $row->actual, '0', 2),
                'state' => (string) $row->state,
            ])->all();
        $expenseCounts = DB::table('expenses')
            ->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $planningYearId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN state = 'open' THEN 1 ELSE 0 END) as open")
            ->selectRaw("SUM(CASE WHEN state = 'closed' THEN 1 ELSE 0 END) as closed")
            ->first();
        $generated = DB::table('expense_rows')
            ->join('expenses', 'expenses.id', '=', 'expense_rows.expense_id')
            ->where('expense_rows.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expense_rows.deleted_at')
            ->whereNull('expenses.deleted_at')
            ->whereNotNull('expense_rows.source_key')
            ->orderByDesc('expense_rows.updated_at')
            ->limit(8)
            ->get(['expenses.id', 'expenses.title', 'expense_rows.updated_at', 'expense_rows.manual_override_at'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'state' => $row->manual_override_at === null ? 'managed' : 'manual',
                'date' => (string) $row->updated_at,
            ])->all();
        $active = DB::table('contracts')
            ->where('tenant_id', $context->tenantId)
            ->whereNull('deleted_at')
            ->where('active', true)
            ->orderBy('title')
            ->limit(8)
            ->get(['id', 'title'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'state' => 'active',
            ])->all();
        $today = now($context->timezone)->toDateString();
        $limit = now($context->timezone)->addYear()->toDateString();
        $renewals = DB::table('contracts')
            ->where('tenant_id', $context->tenantId)
            ->whereNull('deleted_at')
            ->where('active', true)
            ->whereBetween('renewal_date', [$today, $limit])
            ->get(['id', 'title', 'renewal_date as event_date'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'event_type' => 'renewal',
                'date' => (string) $row->event_date,
            ]);
        $ends = DB::table('contract_terms')
            ->join('contracts', fn ($join) => $join
                ->on('contracts.id', '=', 'contract_terms.contract_id')
                ->on('contracts.tenant_id', '=', 'contract_terms.tenant_id'))
            ->where('contract_terms.tenant_id', $context->tenantId)
            ->whereNull('contract_terms.deleted_at')
            ->whereNull('contracts.deleted_at')
            ->where('contracts.active', true)
            ->whereBetween('contract_terms.effective_end', [$today, $limit])
            ->get(['contracts.id', 'contracts.title', 'contract_terms.effective_end as event_date'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'event_type' => 'contract_end',
                'date' => (string) $row->event_date,
            ]);

        return [
            'recentExpenses' => $recent,
            'expenseCounts' => [
                'total' => (int) ($expenseCounts->total ?? 0),
                'open' => (int) ($expenseCounts->open ?? 0),
                'closed' => (int) ($expenseCounts->closed ?? 0),
            ],
            'generatedContractPlanning' => $generated,
            'activeContracts' => $active,
            'upcomingContractEvents' => $renewals->concat($ends)->sortBy('date')->take(8)->values()->all(),
        ];
    }
}
