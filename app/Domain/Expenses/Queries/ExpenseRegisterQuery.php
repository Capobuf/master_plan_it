<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class ExpenseRegisterQuery
{
    /**
     * @return LengthAwarePaginator<int, ExpenseRegisterRow>
     */
    public function paginate(
        User $actor,
        TenantContext $context,
        ?int $planningYearId,
        int $page,
        int $perPage,
        ?ExpenseKind $kind = null,
    ): LengthAwarePaginator {
        $this->policy($context)->viewAny($actor)->authorize();

        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $paginator = $this->registerBuilder($context, $planningYearId, $kind)
            ->orderByRaw('LOWER(expenses.title)')
            ->orderBy('expenses.id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator->through(fn (object $row): ExpenseRegisterRow => new ExpenseRegisterRow(
            id: (int) $row->id,
            planningYearId: (int) $row->planning_year_id,
            planningYearLabel: (int) $row->planning_year_label,
            costCenterId: (int) $row->cost_center_id,
            costCenterName: (string) $row->cost_center_name,
            kind: (string) $row->kind,
            title: (string) $row->title,
            contractId: $row->contract_id === null ? null : (int) $row->contract_id,
            contractTitle: $row->contract_title === null ? null : (string) $row->contract_title,
            contractCurrent: $row->contract_id !== null && $row->contract_deleted_at === null,
            rowCount: (int) $row->row_count,
            netTotal: $this->decimal($row->net_total),
            vatTotal: $this->decimal($row->vat_total),
            grossTotal: $this->decimal($row->gross_total),
        ));
    }

    /** @return array{net: string, vat: string, gross: string} */
    public function totals(User $actor, TenantContext $context, ?int $planningYearId, ?ExpenseKind $kind = null): array
    {
        $this->policy($context)->viewAny($actor)->authorize();

        $totals = DB::table('expense_rows')
            ->join('expenses', function ($join): void {
                $join->on('expenses.id', '=', 'expense_rows.expense_id')
                    ->on('expenses.tenant_id', '=', 'expense_rows.tenant_id');
            })
            ->where('expenses.tenant_id', $context->tenantId)
            ->whereNull('expenses.deleted_at')
            ->whereNull('expense_rows.deleted_at')
            ->when($planningYearId !== null, fn ($query) => $query->where('expenses.planning_year_id', $planningYearId))
            ->when($kind !== null, fn ($query) => $query->where('expenses.kind', $kind->value))
            ->selectRaw('COALESCE(SUM(expense_rows.net_amount), 0) AS net_total')
            ->selectRaw('COALESCE(SUM(expense_rows.vat_amount), 0) AS vat_total')
            ->selectRaw('COALESCE(SUM(expense_rows.gross_amount), 0) AS gross_total')
            ->first();

        return [
            'net' => $this->decimal($totals?->net_total),
            'vat' => $this->decimal($totals?->vat_total),
            'gross' => $this->decimal($totals?->gross_total),
        ];
    }

    /** @return list<array{id: int, label: int, active: bool}> */
    public function yearOptions(User $actor, TenantContext $context): array
    {
        $this->policy($context)->viewAny($actor)->authorize();

        return DB::table('planning_years')
            ->where('tenant_id', $context->tenantId)
            ->orderBy('year_label')
            ->orderBy('id')
            ->get(['id', 'year_label', 'active'])
            ->map(fn (object $year): array => [
                'id' => (int) $year->id,
                'label' => (int) $year->year_label,
                'active' => (bool) $year->active,
            ])
            ->all();
    }

    private function registerBuilder(TenantContext $context, ?int $planningYearId, ?ExpenseKind $kind = null): Builder
    {
        return DB::table('expenses')
            ->join('planning_years', function ($join): void {
                $join->on('planning_years.id', '=', 'expenses.planning_year_id')
                    ->on('planning_years.tenant_id', '=', 'expenses.tenant_id');
            })
            ->join('cost_centers', function ($join): void {
                $join->on('cost_centers.id', '=', 'expenses.cost_center_id')
                    ->on('cost_centers.tenant_id', '=', 'expenses.tenant_id');
            })
            ->leftJoin('expense_rows', function ($join): void {
                $join->on('expense_rows.expense_id', '=', 'expenses.id')
                    ->on('expense_rows.tenant_id', '=', 'expenses.tenant_id')
                    ->whereNull('expense_rows.deleted_at');
            })
            ->leftJoin('contracts', function ($join): void {
                $join->on('contracts.id', '=', 'expenses.contract_id')
                    ->on('contracts.tenant_id', '=', 'expenses.tenant_id');
            })
            ->where('expenses.tenant_id', $context->tenantId)
            ->whereNull('expenses.deleted_at')
            ->when($planningYearId !== null, fn ($query) => $query->where('expenses.planning_year_id', $planningYearId))
            ->when($kind !== null, fn ($query) => $query->where('expenses.kind', $kind->value))
            ->groupBy([
                'expenses.id',
                'expenses.planning_year_id',
                'planning_years.year_label',
                'expenses.cost_center_id',
                'cost_centers.name',
                'expenses.kind',
                'expenses.title',
                'expenses.contract_id',
                'contracts.title',
                'contracts.deleted_at',
            ])
            ->select([
                'expenses.id',
                'expenses.planning_year_id',
                'planning_years.year_label as planning_year_label',
                'expenses.cost_center_id',
                'cost_centers.name as cost_center_name',
                'expenses.kind',
                'expenses.title',
                'expenses.contract_id',
                'contracts.title as contract_title',
                'contracts.deleted_at as contract_deleted_at',
            ])
            ->selectRaw('COUNT(expense_rows.id) AS row_count')
            ->selectRaw('COALESCE(SUM(expense_rows.net_amount), 0) AS net_total')
            ->selectRaw('COALESCE(SUM(expense_rows.vat_amount), 0) AS vat_total')
            ->selectRaw('COALESCE(SUM(expense_rows.gross_amount), 0) AS gross_total');
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    private function decimal(mixed $value): string
    {
        $normalized = (string) ($value ?? '0');
        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        return ($integer === '-0' ? '0' : $integer).'.'.$fraction;
    }
}
