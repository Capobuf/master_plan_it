<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class AnnualBudgetQuery
{
    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, int $planningYearId, ?int $costCenterId = null): array
    {
        $actorQuery = $actor->tenant_id === null
            ? $actor->newQuery()
            : TenantOwnedRecordQuery::forTenant($context, User::class);
        $persistedActor = $actorQuery->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active', true)->first();
        if (! $persistedActor instanceof User || ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== $context->tenantId)) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }

        $basis = $context->budgetBasis->value;
        $rows = DB::table('expenses')
            ->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')->on('projects.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('contracts', fn ($join) => $join->on('contracts.id', '=', 'expenses.contract_id')->on('contracts.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('expense_rows as planned', fn ($join) => $join->on('planned.id', '=', 'expenses.current_planning_row_id')->on('planned.tenant_id', '=', 'expenses.tenant_id')->whereNull('planned.deleted_at'))
            ->leftJoin('vendors', fn ($join) => $join->on('vendors.id', '=', 'planned.vendor_id')->on('vendors.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoinSub(
                DB::table('expense_rows')
                    ->whereNull('deleted_at')->where('type', 'actual')
                    ->groupBy('tenant_id', 'expense_id')
                    ->select(['tenant_id', 'expense_id'])
                    ->selectRaw('SUM(net_amount) AS actual_net')
                    ->selectRaw('SUM(gross_amount) AS actual_gross')
                    ->selectRaw('COUNT(*) AS actual_count'),
                'actuals',
                fn ($join) => $join->on('actuals.expense_id', '=', 'expenses.id')->on('actuals.tenant_id', '=', 'expenses.tenant_id'),
            )
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expenses.deleted_at')
            ->when($costCenterId !== null, fn ($query) => $query->where('expenses.cost_center_id', $costCenterId))
            ->orderBy('expenses.id')
            ->get([
                'expenses.id', 'expenses.title', 'expenses.kind', 'expenses.cost_center_id',
                'cost_centers.name as cost_center_name', 'expenses.project_id', 'projects.title as project_title',
                'expenses.contract_id', 'contracts.title as contract_title', 'expenses.state', 'expenses.closure_outcome',
                'expenses.approved_amount', 'expenses.approved_basis', 'expenses.current_planning_row_id', 'expenses.lock_version',
                'planned.vendor_id', 'vendors.name as vendor_name', 'planned.funded_plafond_expense_id', 'planned.net_amount as planned_net',
                'planned.gross_amount as planned_gross', 'actuals.actual_net', 'actuals.actual_gross', 'actuals.actual_count',
            ])
            ->map(function (object $row) use ($basis): array {
                $planned = $row->{'planned_'.$basis} === null ? null : $this->decimal($row->{'planned_'.$basis});
                $approved = $row->approved_amount === null ? null : $this->decimal($row->approved_amount);
                $actual = $this->decimal($row->{'actual_'.$basis} ?? '0');

                return [
                    'id' => (int) $row->id,
                    'lock_version' => (int) $row->lock_version,
                    'title' => (string) $row->title,
                    'kind' => (string) $row->kind,
                    'cost_center_id' => (int) $row->cost_center_id,
                    'cost_center_name' => (string) $row->cost_center_name,
                    'project_id' => $row->project_id === null ? null : (int) $row->project_id,
                    'project_title' => $row->project_title === null ? null : (string) $row->project_title,
                    'contract_id' => $row->contract_id === null ? null : (int) $row->contract_id,
                    'contract_title' => $row->contract_title === null ? null : (string) $row->contract_title,
                    'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
                    'vendor_name' => $row->vendor_name === null ? null : (string) $row->vendor_name,
                    'state' => (string) $row->state,
                    'closure_outcome' => $row->closure_outcome === null ? null : (string) $row->closure_outcome,
                    'current_planning_row_id' => $row->current_planning_row_id === null ? null : (int) $row->current_planning_row_id,
                    'funded_plafond_expense_id' => $row->funded_plafond_expense_id === null ? null : (int) $row->funded_plafond_expense_id,
                    'planned' => $planned,
                    'approved' => $approved,
                    'approved_basis' => $row->approved_basis === null ? null : (string) $row->approved_basis,
                    'actual' => $actual,
                    'residual' => $approved === null ? null : bcsub($approved, $actual, 2),
                    'variance' => $approved === null ? null : bcsub($actual, $approved, 2),
                    'variance_final' => $row->state === 'closed',
                    'has_actual' => (int) ($row->actual_count ?? 0) > 0,
                ];
            })->all();

        $proposedRaw = '0.00';
        $approvedRaw = '0.00';
        $actualRaw = '0.00';
        $open = 0;
        $closed = 0;
        $unapprovedActual = 0;
        foreach ($rows as $row) {
            $excluded = $row['state'] === 'closed' && in_array($row['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true);
            if (! $excluded && $row['planned'] !== null) {
                $proposedRaw = bcadd($proposedRaw, $row['planned'], 2);
            }
            if ($row['approved'] !== null) {
                $approvedRaw = bcadd($approvedRaw, $row['approved'], 2);
            }
            $actualRaw = bcadd($actualRaw, $row['actual'], 2);
            $row['state'] === 'open' ? $open++ : $closed++;
            if ($row['approved'] === null && $row['has_actual']) {
                $unapprovedActual++;
            }
        }

        [$proposed, $approved, $actual, $plafondOverrun] = $this->reconcilePlafond($rows, $proposedRaw, $approvedRaw, $actualRaw);
        $firstOperationId = DB::table('approval_operations')->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $planningYearId)->orderBy('id')->value('id');
        $initialApproved = $firstOperationId === null ? '0.00' : $this->decimal(
            DB::table('approval_items')->where('approval_operation_id', $firstOperationId)->sum('new_amount'),
        );
        $variations = $this->decimal(DB::table('approval_items')
            ->join('approval_operations', 'approval_operations.id', '=', 'approval_items.approval_operation_id')
            ->where('approval_operations.tenant_id', $context->tenantId)
            ->where('approval_operations.planning_year_id', $planningYearId)
            ->where('approval_operations.kind', 'variation')
            ->sum('approval_items.delta_amount'));

        $budgetState = $year->budget_state instanceof BudgetState ? $year->budget_state->value : (string) $year->budget_state;
        $historyActivatedAt = $year->history_activated_at;

        return [
            'mode' => 'current',
            'requested_as_of' => null,
            'cutoff_utc' => null,
            'read_only' => false,
            'budget' => [
                'planning_year_id' => (int) $year->getKey(),
                'year' => (int) $year->year_label,
                'state' => $budgetState,
                'lock_version' => (int) $year->lock_version,
                'warning' => $budgetState === 'closed' ? 'BUDGET_CLOSED' : null,
                'history_activated_at' => $historyActivatedAt instanceof CarbonInterface ? $historyActivatedAt->toISOString() : null,
            ],
            'summary' => [
                'currency' => $context->currencyCode,
                'official_basis' => $basis,
                'proposed' => $proposed,
                'initial_approved' => $initialApproved,
                'approved_variations' => $variations,
                'approved_current' => $approved,
                'actual' => $actual,
                'residual' => bcsub($approved, $actual, 2),
                'variance' => bcsub($actual, $approved, 2),
                'utilization_percentage' => bccomp($approved, '0', 2) === 1 ? bcdiv(bcmul($actual, '100', 4), $approved, 2) : null,
                'plafond_overrun' => $plafondOverrun,
                'open_expenses' => $open,
                'closed_expenses' => $closed,
                'unapproved_actual_expenses' => $unapprovedActual,
            ],
            'expenses' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{string, string, string, string}
     */
    private function reconcilePlafond(array $rows, string $proposed, string $approved, string $actual): array
    {
        $byId = collect($rows)->keyBy('id');
        $funded = collect($rows)->filter(fn (array $row): bool => $row['funded_plafond_expense_id'] !== null)
            ->groupBy('funded_plafond_expense_id');
        $overrun = '0.00';
        foreach ($funded as $plafondId => $consumers) {
            $plafond = $byId->get((int) $plafondId);
            if (! is_array($plafond) || $plafond['kind'] !== 'plafond') {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $plafondExcluded = $plafond['state'] === 'closed' && in_array($plafond['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true);
            $consumerPlanned = $consumers
                ->reject(fn (array $row): bool => $row['state'] === 'closed' && in_array($row['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true))
                ->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['planned'] ?? '0.00', 2), '0.00');
            $consumerApproved = $consumers->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['approved'] ?? '0.00', 2), '0.00');
            $consumerActual = $consumers->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['actual'], 2), '0.00');
            if (! $plafondExcluded) {
                $proposed = bcsub($proposed, $this->minimum($plafond['planned'] ?? '0.00', $consumerPlanned), 2);
            }
            $approved = bcsub($approved, $this->minimum($plafond['approved'] ?? '0.00', $consumerApproved), 2);
            $available = $plafond['approved'] ?? $plafond['planned'] ?? '0.00';
            if (bccomp($consumerActual, $available, 2) === 1) {
                $overrun = bcadd($overrun, bcsub($consumerActual, $available, 2), 2);
            }
        }

        return [$proposed, $approved, $actual, $overrun];
    }

    private function minimum(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function decimal(mixed $value): string
    {
        $value = (string) ($value ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ($whole === '-0' ? '0' : $whole).'.'.substr(str_pad($fraction, 2, '0'), 0, 2);
    }
}
