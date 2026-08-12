<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Services\ExpenseRelationshipAuthorizer;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateExpense
{
    use ManagesExpenseAggregate;

    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @param  list<array{id: int, lock_version: int}>  $deletedRows
     */
    public function execute(User $actor, TenantContext $context, Expense $target, SaveExpenseData $data, array $rows, string $correlationId, array $deletedRows = []): Expense
    {
        $this->expensePolicy($context)->update($actor, $target)->authorize();
        app(ExpenseRelationshipAuthorizer::class)->authorize(
            $actor,
            $context,
            $data->projectId !== null,
            $data->contractId !== null,
        );
        if ($this->proposedUsesPlafond($rows) || $this->currentFundingPlafondIds($target) !== []) {
            $this->expensePolicy($context)->viewAny($actor)->authorize();
        }
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $rows, $target, $tenant, $deletedRows): Expense {
            $years = app(AnnualEconomicMutationGuard::class)->acquire(
                (int) $tenant->getKey(),
                [$data->planningYearId],
            );
            $expense = Expense::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $expense instanceof Expense || $data->expectedLockVersion === null || $expense->lock_version !== $data->expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            if ((int) $expense->planning_year_id !== $data->planningYearId) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $previousPlafondIds = $this->currentFundingPlafondIds($expense);
            $usesPlafond = $previousPlafondIds !== [] || $this->proposedUsesPlafond($rows);
            if ($usesPlafond) {
                app(PlafondLifecycleGuard::class)->assertPreparation(
                    $years->get($data->planningYearId)->budget_state,
                );
            }
            $currentProjection = $usesPlafond
                ? $this->annualProjection($actor, $context, $data->planningYearId)
                : null;
            if ($expense->approved_amount !== null && (
                (int) $expense->planning_year_id !== $data->planningYearId
                || (int) $expense->cost_center_id !== $data->costCenterId
                || (string) $expense->getRawOriginal('kind') !== $data->kind->value
                || $this->nullableId($expense->project_id) !== $data->projectId
                || $this->nullableId($expense->contract_id) !== $data->contractId
            )) {
                throw new DomainException('APPROVED_DIMENSION_REALLOCATION_REQUIRED');
            }
            $sourceRowIds = $expense->rows()->whereNotNull('source_key')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($sourceRowIds !== []) {
                $submittedIds = array_values(array_filter(array_map(fn ($row) => $row->id, $rows), fn ($id) => $id !== null));
                if ((int) $expense->planning_year_id !== $data->planningYearId
                    || (int) $expense->contract_id !== (int) $data->contractId
                    || $this->nullableId($expense->project_id) !== $data->projectId) {
                    throw new DomainException('TENANT_RELATION_MISMATCH');
                }
                if (array_diff($sourceRowIds, $submittedIds) !== []) {
                    throw ValidationException::withMessages([
                        'rows' => 'The submitted expense rows are invalid.',
                    ]);
                }
            }
            $changed = $this->saveAggregate($expense, $tenant, $data, $rows, $actor, $deletedRows);
            if ($currentProjection !== null) {
                $this->assertCoveredRowsHaveCapacity(
                    $currentProjection,
                    $this->annualProjection($actor, $context, $data->planningYearId),
                    $expense,
                    $rows,
                    $previousPlafondIds,
                );
            }
            if ($changed === []) {
                return $expense->fresh(['rows']);
            }
            $this->revisions($actor, $context, RevisionOperation::Update, $correlationId, $expense, $changed);
            $this->audit('expense.updated', $correlationId, $actor, $tenant, $expense, ['rows' => count($rows)]);
            $this->purgeDeletedRowAttachments($actor, $context, $deletedRows, $correlationId);

            return $expense->fresh(['rows']);
        });
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
