<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\PlanningYear;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class EconomicDatasetQuery
{
    public function execute(User $actor, TenantContext $context, int $planningYearId): EconomicDataset
    {
        $persistedActor=User::query()->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active',true)->first();
        if(!$persistedActor instanceof User||($persistedActor->tenant_id!==null&&(int)$persistedActor->tenant_id!==$context->tenantId)){throw new \Illuminate\Auth\Access\AuthorizationException('TENANT_CONTEXT_REQUIRED');}
        $year = PlanningYear::query()->where('tenant_id', $context->tenantId)->find($planningYearId);
        if (! $year instanceof PlanningYear) { throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]); }
        $rows = DB::table('expense_rows')->join('expenses', fn ($join) => $join->on('expenses.id', '=', 'expense_rows.expense_id')->on('expenses.tenant_id', '=', 'expense_rows.tenant_id'))->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))->where('expenses.tenant_id', $context->tenantId)->where('expenses.planning_year_id', $planningYearId)->whereNull('expenses.deleted_at')->whereNull('expense_rows.deleted_at')->orderBy('expense_rows.id')->get(['expenses.id as expense_id', 'expense_rows.id as row_id', 'expenses.kind as expense_kind', 'expense_rows.type', 'expense_rows.confirmation_state', 'expenses.cost_center_id', 'cost_centers.name as cost_center_name', 'expense_rows.net_amount', 'expense_rows.vat_amount', 'expense_rows.gross_amount', 'expense_rows.funded_plafond_expense_id', 'expense_rows.spend_date', 'expense_rows.period_start', 'expense_rows.period_end', 'expense_rows.distribution']);
        return new EconomicDataset(
            new EconomicScope($context->tenantId, (int) $year->getKey(), (int) $year->year_label, $context->currencyCode, $context->tenant->budget_basis),
            $rows->map(fn ($row) => new EconomicLine((int) $row->expense_id, (int) $row->row_id, (string) $row->expense_kind, (string) $row->type, $row->confirmation_state === null ? null : (string) $row->confirmation_state, (int) $row->cost_center_id, (string) $row->cost_center_name, (string) $row->net_amount, (string) $row->vat_amount, (string) $row->gross_amount, $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id, $row->spend_date === null ? null : (string) $row->spend_date, $row->period_start === null ? null : (string) $row->period_start, $row->period_end === null ? null : (string) $row->period_end, $row->distribution === null ? null : (string) $row->distribution))->all(),
        );
    }
}
