<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use Carbon\CarbonInterface;
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
        if (! $persistedActor instanceof User
            || ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== $context->tenantId)) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }

        $dataset = app(EconomicDatasetQuery::class)->execute($actor, $context, $planningYearId);
        /** @var AnnualEconomicProjection $projection */
        $projection = app(EconomicEngine::class)->project($dataset);

        $metadata = DB::table('expenses')
            ->join('cost_centers', fn ($join) => $join->on('cost_centers.id', '=', 'expenses.cost_center_id')->on('cost_centers.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')->on('projects.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('contracts', fn ($join) => $join->on('contracts.id', '=', 'expenses.contract_id')->on('contracts.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('expense_rows as planned', fn ($join) => $join->on('planned.id', '=', 'expenses.current_planning_row_id')->on('planned.tenant_id', '=', 'expenses.tenant_id')->whereNull('planned.deleted_at'))
            ->leftJoin('vendors', fn ($join) => $join->on('vendors.id', '=', 'planned.vendor_id')->on('vendors.tenant_id', '=', 'expenses.tenant_id'))
            ->where('expenses.tenant_id', $context->tenantId)
            ->where('expenses.planning_year_id', $planningYearId)
            ->whereNull('expenses.deleted_at')
            ->when($costCenterId !== null, fn ($query) => $query->where('expenses.cost_center_id', $costCenterId))
            ->orderBy('expenses.id')
            ->get([
                'expenses.id', 'expenses.title', 'expenses.kind', 'expenses.cost_center_id',
                'cost_centers.name as cost_center_name', 'expenses.project_id', 'projects.title as project_title',
                'expenses.contract_id', 'contracts.title as contract_title', 'expenses.approved_amount',
                'expenses.approved_basis', 'expenses.current_planning_row_id', 'expenses.lock_version',
                'planned.vendor_id', 'vendors.name as vendor_name', 'planned.funded_plafond_expense_id',
            ]);

        $rows = [];
        $approvedCurrent = '0.00';
        $unapprovedActual = 0;
        $selectedPlanning = EconomicMeasure::zero($projection->basis);
        $selectedActual = EconomicMeasure::zero($projection->basis);
        foreach ($metadata as $record) {
            $expenseProjection = $projection->expenses[(int) $record->id] ?? null;
            if ($expenseProjection === null) {
                throw new \DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $approved = $record->approved_amount === null ? null : $this->decimal($record->approved_amount);
            $actual = $expenseProjection->actual->official;
            foreach ($expenseProjection->lines as $line) {
                if ($line->contributesToCurrentPlanning) {
                    $selectedPlanning = $selectedPlanning->plus($line->amount, $projection->basis);
                }
            }
            $selectedActual = $selectedActual->plus($expenseProjection->actual, $projection->basis);
            if ($approved !== null) {
                $approvedCurrent = bcadd($approvedCurrent, $approved, 2);
            } elseif (bccomp($actual, '0.00', 2) !== 0) {
                $unapprovedActual++;
            }

            $rows[] = [
                'id' => (int) $record->id,
                'lock_version' => (int) $record->lock_version,
                'title' => (string) $record->title,
                'kind' => (string) $record->kind,
                'cost_center_id' => (int) $record->cost_center_id,
                'cost_center_name' => (string) $record->cost_center_name,
                'project_id' => $record->project_id === null ? null : (int) $record->project_id,
                'project_title' => $record->project_title === null ? null : (string) $record->project_title,
                'contract_id' => $record->contract_id === null ? null : (int) $record->contract_id,
                'contract_title' => $record->contract_title === null ? null : (string) $record->contract_title,
                'vendor_id' => $record->vendor_id === null ? null : (int) $record->vendor_id,
                'vendor_name' => $record->vendor_name === null ? null : (string) $record->vendor_name,
                'current_planning_row_id' => $record->current_planning_row_id === null ? null : (int) $record->current_planning_row_id,
                'funded_plafond_expense_id' => $record->funded_plafond_expense_id === null ? null : (int) $record->funded_plafond_expense_id,
                'currency' => $projection->currency,
                'basis' => $projection->basis,
                'totals' => [
                    'current_planning' => $this->measure($expenseProjection->currentPlanning),
                    'actual' => $this->measure($expenseProjection->actual),
                ],
                'plafond_measures' => isset($projection->plafonds[(int) $record->id])
                    ? $this->plafondMeasures($projection->plafonds[(int) $record->id])
                    : null,
                'planned' => $record->kind === 'plafond' || $expenseProjection->currentPlanningRowId !== null
                    ? $this->contributivePlanning($expenseProjection->lines, $projection->basis)->official
                    : null,
                'approved' => $approved,
                'approved_basis' => $record->approved_basis === null ? null : (string) $record->approved_basis,
                'actual' => $actual,
                'residual' => $approved === null ? null : bcsub($approved, $actual, 2),
                'variance' => $approved === null ? null : bcsub($actual, $approved, 2),
                'has_actual' => bccomp($actual, '0.00', 2) !== 0,
                'lines' => array_map(fn (ProjectedEconomicLine $line): array => $this->line($line), $expenseProjection->lines),
            ];
        }

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
            'currency' => $projection->currency,
            'basis' => $projection->basis,
            'totals' => [
                'current_planning' => $this->measure($selectedPlanning),
                'actual' => $this->measure($selectedActual),
            ],
            'summary' => [
                'currency' => $projection->currency,
                'official_basis' => $projection->basis,
                'proposed' => $selectedPlanning->official,
                'initial_approved' => $initialApproved,
                'approved_variations' => $variations,
                'approved_current' => $approvedCurrent,
                'actual' => $selectedActual->official,
                'residual' => bcsub($approvedCurrent, $selectedActual->official, 2),
                'variance' => bcsub($selectedActual->official, $approvedCurrent, 2),
                'utilization_percentage' => bccomp($approvedCurrent, '0', 2) === 1
                    ? bcdiv(bcmul($selectedActual->official, '100', 4), $approvedCurrent, 2)
                    : null,
                'unapproved_actual_expenses' => $unapprovedActual,
            ],
            'plafonds' => array_values(array_map(fn ($plafond): array => [
                'id' => $plafond->plafondExpenseId,
                'planning_year_id' => $plafond->planningYearId,
                'title' => $plafond->title,
                'cost_center' => ['id' => $plafond->costCenterId, 'name' => $plafond->costCenterName],
                'currency' => $plafond->currency,
                'basis' => $plafond->basis,
                'measures' => $this->plafondMeasures($plafond),
            ], array_filter(
                $projection->plafonds,
                static fn ($plafond): bool => $costCenterId === null || $plafond->costCenterId === $costCenterId,
            ))),
            'expenses' => $rows,
        ];
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }

    /** @return array<string, array{net: string, vat: string, gross: string, official: string}> */
    private function plafondMeasures(PlafondEconomicProjection $plafond): array
    {
        return [
            'allocation' => $this->measure($plafond->allocation),
            'coverage_planned' => $this->measure($plafond->coveragePlanned),
            'consumed' => $this->measure($plafond->consumed),
            'available' => $this->measure($plafond->available),
        ];
    }

    /** @return array<string, mixed> */
    private function line(ProjectedEconomicLine $line): array
    {
        return [
            'expense_id' => $line->expenseId,
            'row_id' => $line->rowId,
            'planning_year_id' => $line->planningYearId,
            'economic_year_label' => $line->economicYearLabel,
            'type' => $line->type,
            'is_current_planning' => $line->isCurrentPlanning,
            'contributes_to_current_planning' => $line->contributesToCurrentPlanning,
            'description' => $line->description,
            'notes' => $line->notes,
            'spend_date' => $line->spendDate,
            'cost_center_id' => $line->costCenterId,
            'vendor_id' => $line->vendorId,
            'vendor_name' => $line->vendorName,
            'project_id' => $line->projectId,
            'contract_id' => $line->contractId,
            'amount' => $this->measure($line->amount),
        ];
    }

    /** @param list<ProjectedEconomicLine> $lines */
    private function contributivePlanning(array $lines, string $basis): EconomicMeasure
    {
        $planning = EconomicMeasure::zero($basis);
        foreach ($lines as $line) {
            if ($line->contributesToCurrentPlanning) {
                $planning = $planning->plus($line->amount, $basis);
            }
        }

        return $planning;
    }

    private function decimal(mixed $value): string
    {
        $value = (string) ($value ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ($whole === '-0' ? '0' : $whole).'.'.substr(str_pad($fraction, 2, '0'), 0, 2);
    }
}
