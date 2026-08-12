<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class EconomicDatasetQuery
{
    public function execute(
        User $actor,
        TenantContext $context,
        int $planningYearId,
        ?EconomicReportFilterData $filters = null,
        ?BudgetBasis $basisOverride = null,
    ): EconomicDataset {
        $actorQuery = $actor->tenant_id === null
            ? $actor->newQuery()
            : TenantOwnedRecordQuery::forTenant($context, User::class);
        $persistedActor = $actorQuery
            ->whereKey($actor->getRawOriginal($actor->getKeyName()))
            ->where('is_active', true)
            ->first();
        if (! $persistedActor instanceof User || ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== $context->tenantId)) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->join('tenants', 'tenants.id', '=', 'planning_years.tenant_id')
            ->select([
                'planning_years.*',
                'tenants.currency_code as scope_currency_code',
                'tenants.budget_basis as scope_budget_basis',
            ])
            ->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }
        $rows = DB::table('expense_rows')
            ->join('expenses', fn ($join) => $join->on('expenses.id', '=', 'expense_rows.expense_id')->on('expenses.tenant_id', '=', 'expense_rows.tenant_id'))
            ->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('vendors', fn ($join) => $join->on('vendors.id', '=', 'expense_rows.vendor_id')->on('vendors.tenant_id', '=', 'expense_rows.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')->on('projects.tenant_id', '=', 'expenses.tenant_id')->whereNull('projects.deleted_at'))
            ->leftJoin('users as row_creators', 'row_creators.id', '=', 'expense_rows.created_by_user_id')
            ->leftJoin('expenses as funded_plafonds', function ($join): void {
                $join->on('funded_plafonds.id', '=', 'expense_rows.funded_plafond_expense_id')
                    ->on('funded_plafonds.tenant_id', '=', 'expense_rows.tenant_id')
                    ->whereNull('funded_plafonds.deleted_at');
            })
            ->leftJoin('cost_centers as plafond_cost_centers', function ($join): void {
                $join->on('plafond_cost_centers.id', '=', 'funded_plafonds.cost_center_id')
                    ->on('plafond_cost_centers.tenant_id', '=', 'funded_plafonds.tenant_id');
            })
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expenses.deleted_at')
            ->whereNull('expense_rows.deleted_at')
            ->orderBy('expense_rows.id')
            ->get([
                'expenses.id as expense_id', 'expenses.current_planning_row_id', 'expense_rows.id as row_id', 'expenses.kind as expense_kind',
                'expense_rows.type', 'expense_rows.confirmation_state', 'expenses.cost_center_id',
                'cost_centers.name as cost_center_name', 'expense_rows.net_amount', 'expense_rows.vat_amount',
                'expense_rows.gross_amount', 'expense_rows.is_extra', 'expense_rows.funded_plafond_expense_id',
                'expense_rows.spend_date', 'expense_rows.period_start', 'expense_rows.period_end',
                'expense_rows.distribution', 'expenses.project_id', 'projects.stage as project_stage',
                'projects.title as project_title',
                'expense_rows.description', 'expense_rows.notes', 'expense_rows.vendor_id',
                'vendors.name as vendor_name', 'expenses.contract_id',
                'expenses.title as expense_title', 'expense_rows.created_by_user_id',
                'row_creators.name as created_by_user_name', 'funded_plafonds.title as funded_plafond_title',
                'funded_plafonds.cost_center_id as funded_plafond_cost_center_id',
                'plafond_cost_centers.name as funded_plafond_cost_center_name',
            ]);

        return new EconomicDataset(
            new EconomicScope(
                $context->tenantId,
                (int) $year->getKey(),
                (int) $year->year_label,
                (string) $year->getAttribute('scope_currency_code'),
                $basisOverride ?? BudgetBasis::from((string) $year->getAttribute('scope_budget_basis')),
            ),
            $rows->map(fn ($row) => new EconomicLine((int) $row->expense_id, (int) $row->row_id, (string) $row->expense_kind, (string) $row->type, $row->confirmation_state === null ? null : (string) $row->confirmation_state, (int) $row->cost_center_id, (string) $row->cost_center_name, (string) $row->net_amount, (string) $row->vat_amount, (string) $row->gross_amount, $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id, $row->spend_date === null ? null : (string) $row->spend_date, $row->period_start === null ? null : (string) $row->period_start, $row->period_end === null ? null : (string) $row->period_end, $row->distribution === null ? null : (string) $row->distribution, (bool) $row->is_extra, $row->project_id === null ? null : (int) $row->project_id, $row->project_stage === null ? null : (string) $row->project_stage, $row->project_title === null ? null : (string) $row->project_title, (int) $row->current_planning_row_id === (int) $row->row_id, (string) $row->description, $row->notes === null ? null : (string) $row->notes, $row->vendor_id === null ? null : (int) $row->vendor_id, $row->vendor_name === null ? null : (string) $row->vendor_name, $row->contract_id === null ? null : (int) $row->contract_id, (string) $row->expense_title, $row->created_by_user_id === null ? null : (int) $row->created_by_user_id, $row->created_by_user_name === null ? null : (string) $row->created_by_user_name, $row->funded_plafond_title === null ? null : (string) $row->funded_plafond_title, $row->funded_plafond_cost_center_id === null ? null : (int) $row->funded_plafond_cost_center_id, $row->funded_plafond_cost_center_name === null ? null : (string) $row->funded_plafond_cost_center_name))->all(),
        );
    }
}
