<?php

namespace App\Domain\Plafonds\Actions;

use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Services\PlafondCapacityService;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use App\Domain\Plafonds\Data\SavePlafondData;
use App\Domain\Plafonds\Services\PlafondRelationshipAuthorizer;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreatePlafond
{
    use ManagesExpenseAggregate;

    public function execute(
        User $actor,
        TenantContext $context,
        SavePlafondData $data,
        string $correlationId,
    ): Expense {
        $this->expensePolicy($context)->create($actor)->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        try {
            return DB::transaction(function () use ($actor, $context, $correlationId, $data, $tenant): Expense {
                $years = app(AnnualEconomicMutationGuard::class)->acquire(
                    (int) $tenant->getKey(),
                    [$data->planningYearId],
                );
                app(PlafondLifecycleGuard::class)->assertPreparation(
                    $years->get($data->planningYearId)->budget_state,
                );

                $save = new SaveExpenseData(
                    planningYearId: $data->planningYearId,
                    costCenterId: $data->costCenterId,
                    kind: ExpenseKind::Plafond,
                    title: $data->title,
                    notes: $data->notes,
                    projectId: null,
                    contractId: null,
                    expectedLockVersion: null,
                );
                $rows = [$data->initialAllocation->toExpenseRow(1)];
                $expense = new Expense;
                $changed = $this->saveAggregate($expense, $tenant, $save, $rows, $actor);
                app(PlafondCapacityService::class)->assertAnnualProjectionSufficient(
                    $this->annualProjection($actor, $context, $data->planningYearId),
                    field: 'initial_allocation.entered_amount',
                );
                $this->revisions(
                    $actor,
                    $context,
                    RevisionOperation::Create,
                    $correlationId,
                    $expense,
                    $changed,
                );
                $this->audit(
                    'expense.plafond.created',
                    $correlationId,
                    $actor,
                    $tenant,
                    $expense,
                    ['adjustments' => 1],
                );

                return $expense->fresh(['rows']);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000'
                && str_contains($exception->getMessage(), 'expenses_live_plafond_slot_unique')) {
                throw ValidationException::withMessages([
                    'cost_center_id' => 'A current Plafond already exists for this planning year and cost center.',
                ]);
            }

            throw $exception;
        }
    }
}
