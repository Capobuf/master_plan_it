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
     * @return array<string, list<array<string, mixed>>>
     */
    public function apiDataset(TenantContext $context, int $planningYearId): array
    {
        $recent = DB::table('expenses')
            ->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $planningYearId)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'date' => (string) $row->updated_at,
            ])->all();
        $generated = DB::table('expense_rows')
            ->join('expenses', 'expenses.id', '=', 'expense_rows.expense_id')
            ->where('expense_rows.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expense_rows.deleted_at')
            ->whereNull('expenses.deleted_at')
            ->whereNotNull('expense_rows.source_key')
            ->where('expense_rows.confirmation_state', 'to_confirm')
            ->orderBy('expense_rows.spend_date')
            ->limit(8)
            ->get(['expenses.id', 'expenses.title', 'expense_rows.spend_date'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'label' => (string) $row->title,
                'state' => 'to_confirm',
                'date' => (string) $row->spend_date,
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
            'generatedExpensesToConfirm' => $generated,
            'activeContracts' => $active,
            'upcomingContractEvents' => $renewals->concat($ends)->sortBy('date')->take(8)->values()->all(),
        ];
    }

    /** @return array<string,list<array<string,mixed>>> */
    public function presentation(TenantContext $context, int $planningYearId): array
    {
        $dataset = $this->apiDataset($context, $planningYearId);

        return [
            'recentExpenses' => array_map(fn (array $item): array => [
                ...$item,
                'href' => route('operational.expenses.show', $item['id']),
            ], $dataset['recentExpenses']),
            'generatedExpensesToConfirm' => array_map(fn (array $item): array => [
                ...$item,
                'href' => route('operational.expenses.show', $item['id']),
                'state' => 'To confirm',
            ], $dataset['generatedExpensesToConfirm']),
            'activeContracts' => array_map(fn (array $item): array => [
                ...$item,
                'href' => route('operational.contracts.show', $item['id']),
                'state' => 'Active',
            ], $dataset['activeContracts']),
            'upcomingContractEvents' => array_map(fn (array $item): array => [
                ...$item,
                'href' => route('operational.contracts.show', $item['id']),
                'secondary' => $item['event_type'] === 'renewal' ? 'Renewal' : 'Contract end',
                'event_type' => null,
            ], $dataset['upcomingContractEvents']),
        ];
    }
}
