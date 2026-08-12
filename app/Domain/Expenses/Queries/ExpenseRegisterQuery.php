<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Expenses\Data\ExpenseRegisterFilterData;
use App\Domain\Expenses\Data\ExpenseRegisterRow;
use App\Domain\Expenses\Services\ExpenseRelationshipAuthorizer;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
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
    /** @return LengthAwarePaginator<int, ExpenseRegisterRow> */
    public function paginate(
        User $actor,
        TenantContext $context,
        ExpenseRegisterFilterData $filters,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $this->policy($context)->viewAny($actor)->authorize();
        $this->authorizeRelationships($actor, $context, $filters);

        /** @var AnnualEconomicProjection $projection */
        $projection = app(EconomicEngine::class)->project(
            app(EconomicDatasetQuery::class)->execute($actor, $context, $filters->planningYearId),
        );

        $paginator = $this->registerBuilder($context, $filters)
            ->orderByRaw('LOWER(expenses.title)')
            ->orderBy('expenses.id')
            ->paginate(max(1, min($perPage, 100)), ['*'], 'page', max(1, $page));

        return $paginator->through(function (object $row) use ($projection): ExpenseRegisterRow {
            $expenseProjection = $projection->expenses[(int) $row->id] ?? null;
            if ($expenseProjection === null) {
                throw new \DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }

            return new ExpenseRegisterRow(
                id: (int) $row->id,
                planningYearId: (int) $row->planning_year_id,
                economicYearLabel: (int) $row->planning_year_label,
                costCenterId: (int) $row->cost_center_id,
                costCenterName: (string) $row->cost_center_name,
                kind: (string) $row->kind,
                title: (string) $row->title,
                projectId: $row->project_id === null ? null : (int) $row->project_id,
                projectTitle: $row->project_title === null ? null : (string) $row->project_title,
                projectCurrent: $row->project_id !== null && $row->project_deleted_at === null,
                contractId: $row->contract_id === null ? null : (int) $row->contract_id,
                contractTitle: $row->contract_title === null ? null : (string) $row->contract_title,
                contractCurrent: $row->contract_id !== null && $row->contract_deleted_at === null,
                currentPlanningRowId: $row->current_planning_row_id === null ? null : (int) $row->current_planning_row_id,
                vendorCount: (int) $row->vendor_count,
                vendorSummary: match (true) {
                    (int) $row->vendor_count === 0 => '—',
                    (int) $row->vendor_count === 1 => (string) ($row->single_vendor_name ?? '—'),
                    default => (int) $row->vendor_count.' fornitori',
                },
                rowCount: (int) $row->row_count,
                lockVersion: (int) $row->lock_version,
                currency: $projection->currency,
                basis: $projection->basis,
                totals: [
                    'current_planning' => $this->measure($expenseProjection->currentPlanning),
                    'actual' => $this->measure($expenseProjection->actual),
                ],
            );
        });
    }

    /** @return array{current_planning: array<string, string>, actual: array<string, string>} */
    public function totals(User $actor, TenantContext $context, ExpenseRegisterFilterData $filters): array
    {
        $this->policy($context)->viewAny($actor)->authorize();
        $this->authorizeRelationships($actor, $context, $filters);

        /** @var AnnualEconomicProjection $projection */
        $projection = app(EconomicEngine::class)->project(
            app(EconomicDatasetQuery::class)->execute($actor, $context, $filters->planningYearId),
        );
        $ids = $this->filteredExpenseIds($context, $filters)->pluck('expenses.id')->map(fn ($id): int => (int) $id);
        $currentPlanning = EconomicMeasure::zero($projection->basis);
        $actual = EconomicMeasure::zero($projection->basis);
        foreach ($ids as $id) {
            $expenseProjection = $projection->expenses[$id] ?? null;
            if ($expenseProjection !== null) {
                $currentPlanning = $currentPlanning->plus($expenseProjection->currentPlanning, $projection->basis);
                $actual = $actual->plus($expenseProjection->actual, $projection->basis);
            }
        }

        return [
            'current_planning' => $this->measure($currentPlanning),
            'actual' => $this->measure($actual),
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

    private function registerBuilder(TenantContext $context, ExpenseRegisterFilterData $filters): Builder
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
            ->leftJoin('vendors', function ($join): void {
                $join->on('vendors.id', '=', 'expense_rows.vendor_id')
                    ->on('vendors.tenant_id', '=', 'expense_rows.tenant_id');
            })
            ->leftJoin('contracts', function ($join): void {
                $join->on('contracts.id', '=', 'expenses.contract_id')
                    ->on('contracts.tenant_id', '=', 'expenses.tenant_id');
            })
            ->leftJoin('projects', function ($join): void {
                $join->on('projects.id', '=', 'expenses.project_id')
                    ->on('projects.tenant_id', '=', 'expenses.tenant_id');
            })
            ->whereIn('expenses.id', $this->filteredExpenseIds($context, $filters))
            ->groupBy([
                'expenses.id', 'expenses.planning_year_id', 'planning_years.year_label',
                'expenses.cost_center_id', 'cost_centers.name', 'expenses.kind',
                'expenses.title', 'expenses.project_id', 'projects.title', 'projects.deleted_at',
                'expenses.contract_id', 'contracts.title', 'contracts.deleted_at',
                'expenses.current_planning_row_id', 'expenses.lock_version',
            ])
            ->select([
                'expenses.id', 'expenses.planning_year_id',
                'planning_years.year_label as planning_year_label', 'expenses.cost_center_id',
                'cost_centers.name as cost_center_name', 'expenses.kind',
                'expenses.title', 'expenses.project_id', 'projects.title as project_title',
                'projects.deleted_at as project_deleted_at', 'expenses.contract_id',
                'contracts.title as contract_title', 'contracts.deleted_at as contract_deleted_at',
                'expenses.current_planning_row_id', 'expenses.lock_version',
            ])
            ->selectRaw('COUNT(expense_rows.id) AS row_count')
            ->selectRaw('COUNT(DISTINCT expense_rows.vendor_id) AS vendor_count')
            ->selectRaw('MIN(vendors.name) AS single_vendor_name');
    }

    private function authorizeRelationships(User $actor, TenantContext $context, ExpenseRegisterFilterData $filters): void
    {
        $relationships = DB::table('expenses')
            ->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $filters->planningYearId)
            ->whereNull('deleted_at')
            ->selectRaw('MAX(project_id IS NOT NULL) AS has_project, MAX(contract_id IS NOT NULL) AS has_contract')
            ->first();

        app(ExpenseRelationshipAuthorizer::class)->authorize(
            $actor,
            $context,
            $filters->projectId !== null || (bool) $relationships->has_project,
            $filters->contractId !== null || (bool) $relationships->has_contract,
        );
    }

    private function filteredExpenseIds(TenantContext $context, ExpenseRegisterFilterData $filters): Builder
    {
        $query = DB::table('expenses')
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $filters->planningYearId)
            ->whereNull('expenses.deleted_at')
            ->when($filters->kind !== null, fn (Builder $builder) => $builder->where('expenses.kind', $filters->kind->value))
            ->when($filters->costCenterId !== null, fn (Builder $builder) => $builder->where('expenses.cost_center_id', $filters->costCenterId))
            ->when($filters->projectId !== null, fn (Builder $builder) => $builder->where('expenses.project_id', $filters->projectId))
            ->when($filters->contractId !== null, fn (Builder $builder) => $builder->where('expenses.contract_id', $filters->contractId));

        if ($filters->query !== null) {
            $needle = addcslashes(mb_strtolower($filters->query), '\\%_');
            $query->whereRaw('LOWER(expenses.title) LIKE ?', ['%'.$needle.'%']);
        }

        if ($filters->vendorId !== null) {
            $query->whereExists(function (Builder $rows) use ($filters): void {
                $rows->selectRaw('1')
                    ->from('expense_rows')
                    ->whereColumn('expense_rows.expense_id', 'expenses.id')
                    ->whereColumn('expense_rows.tenant_id', 'expenses.tenant_id')
                    ->where('expense_rows.vendor_id', $filters->vendorId)
                    ->whereNull('expense_rows.deleted_at');
            });
        }

        return $query->select('expenses.id');
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy($context, app(PermissionRegistrar::class), app(PlatformAdministrator::class));
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }
}
