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
use Illuminate\Support\Facades\DB;

final class CreateExpense
{
    use ManagesExpenseAggregate;

    /** @param list<SaveExpenseRowData> $rows */
    public function execute(User $actor, TenantContext $context, SaveExpenseData $data, array $rows, string $correlationId): Expense
    {
        $this->expensePolicy($context)->create($actor)->authorize();
        app(ExpenseRelationshipAuthorizer::class)->authorize(
            $actor,
            $context,
            $data->projectId !== null,
            $data->contractId !== null,
        );
        if ($this->proposedUsesPlafond($rows)) {
            $this->expensePolicy($context)->viewAny($actor)->authorize();
        }
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $data, $rows, $tenant): Expense {
            $years = app(AnnualEconomicMutationGuard::class)->acquire(
                (int) $tenant->getKey(),
                [$data->planningYearId],
            );
            $usesPlafond = $this->proposedUsesPlafond($rows);
            if ($usesPlafond) {
                app(PlafondLifecycleGuard::class)->assertPreparation(
                    $years->get($data->planningYearId)->budget_state,
                );
            }
            $currentProjection = $usesPlafond
                ? $this->annualProjection($actor, $context, $data->planningYearId)
                : null;
            $expense = new Expense;
            $changed = $this->saveAggregate($expense, $tenant, $data, $rows, $actor);
            if ($currentProjection !== null) {
                $this->assertCoveredRowsHaveCapacity(
                    $currentProjection,
                    $this->annualProjection($actor, $context, $data->planningYearId),
                    $expense,
                    $rows,
                );
            }
            $this->revisions($actor, $context, RevisionOperation::Create, $correlationId, $expense, $changed);
            $this->audit('expense.created', $correlationId, $actor, $tenant, $expense, ['rows' => count($rows)]);

            return $expense->fresh(['rows']);
        });
    }
}
