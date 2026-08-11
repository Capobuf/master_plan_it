<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class MoveExpense
{
    use ManagesExpenseAggregate;

    /** @return array{Expense, Expense} */
    public function execute(
        User $actor,
        TenantContext $context,
        Expense $target,
        int $expectedLockVersion,
        int $targetPlanningYearId,
        string $correlationId,
    ): array {
        $policy = $this->expensePolicy($context);
        $policy->update($actor, $target)->authorize();
        $policy->create($actor)->authorize();
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $target, $targetPlanningYearId, $tenant): array {
            $expense = Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()
                ->find($target->getKey());

            if (! $expense instanceof Expense || (int) $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            if ((int) $expense->planning_year_id === $targetPlanningYearId) {
                throw new DomainException('EXPENSE_MOVE_REQUIRES_DIFFERENT_YEAR');
            }

            $yearIds = [(int) $expense->planning_year_id, $targetPlanningYearId];
            sort($yearIds);
            $years = PlanningYear::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $yearIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (PlanningYear $year): int => (int) $year->getKey());
            $targetYear = $years->get($targetPlanningYearId);
            if (! $targetYear instanceof PlanningYear || ! $targetYear->active) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            if (Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('moved_from_expense_id', $expense->getKey())
                ->exists()) {
                throw new DomainException('EXPENSE_ALREADY_MOVED');
            }

            $planningRows = $expense->rows()
                ->whereIn('type', [ExpenseType::Estimate->value, ExpenseType::Quote->value])
                ->lockForUpdate()
                ->orderBy('position')
                ->orderBy('id')
                ->get();
            if ($planningRows->isEmpty()) {
                throw new DomainException('EXPENSE_MOVE_REQUIRES_PLANNING');
            }

            $rowData = $planningRows->map(fn (ExpenseRow $row): SaveExpenseRowData => new SaveExpenseRowData(
                id: null,
                position: (int) $row->position,
                vendorId: $row->vendor_id === null ? null : (int) $row->vendor_id,
                type: ExpenseType::from((string) $row->getRawOriginal('type')),
                description: (string) $row->description,
                quantity: $this->nullableString($row->getRawOriginal('quantity')),
                unitPrice: $this->nullableString($row->getRawOriginal('unit_price')),
                enteredAmount: (string) $row->getRawOriginal('entered_amount'),
                amountIncludesVat: (bool) $row->amount_includes_vat,
                vatRate: (string) $row->vat_rate,
                isExtra: (bool) $row->is_extra,
                fundedPlafondExpenseId: null,
                spendDate: $this->movePlanningDate($row->spend_date, (int) $targetYear->year_label),
                periodStart: null,
                periodEnd: null,
                distribution: null,
                externalReference: $row->external_reference,
                expectedLockVersion: null,
                isCurrentPlanning: (int) $row->getKey() === (int) $expense->current_planning_row_id,
            ))->all();

            $destination = new Expense;
            $changed = $this->saveAggregate(
                $destination,
                $tenant,
                new SaveExpenseData(
                    planningYearId: (int) $targetYear->getKey(),
                    costCenterId: (int) $expense->cost_center_id,
                    kind: ExpenseKind::from((string) $expense->getRawOriginal('kind')),
                    title: (string) $expense->title,
                    notes: $expense->notes,
                    projectId: $expense->project_id === null ? null : (int) $expense->project_id,
                    contractId: $expense->contract_id === null ? null : (int) $expense->contract_id,
                    expectedLockVersion: null,
                ),
                $rowData,
                $actor,
            );
            $destination->forceFill(['moved_from_expense_id' => $expense->getKey()])->save();

            $expense->forceFill([
                'state' => ExpenseState::Closed,
                'closure_outcome' => ExpenseClosureOutcome::Moved,
                'closed_at' => CarbonImmutable::now('UTC'),
                'closed_by_user_id' => $actor->getKey(),
                'lock_version' => (int) $expense->lock_version + 1,
            ])->save();

            $this->revisions(
                $actor,
                $context,
                RevisionOperation::Update,
                $correlationId,
                $expense,
                [$expense, ...$changed],
            );
            $this->audit('expense.moved', $correlationId, $actor, $tenant, $expense, [
                'destination_expense_id' => $destination->getKey(),
                'target_planning_year_id' => $targetYear->getKey(),
            ]);

            return [
                $expense->fresh(['rows', 'currentPlanningRow']),
                $destination->fresh(['rows', 'currentPlanningRow']),
            ];
        });
    }

    private function movePlanningDate(mixed $value, int $targetYear): ?string
    {
        if ($value === null) {
            return null;
        }

        $source = CarbonImmutable::parse((string) $value);
        $lastDay = CarbonImmutable::create($targetYear, $source->month, 1)->daysInMonth;

        return CarbonImmutable::create($targetYear, $source->month, min($source->day, $lastDay))->toDateString();
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
