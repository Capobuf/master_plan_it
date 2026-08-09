<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CloseExpense
{
    use ManagesExpenseAggregate;

    public function execute(
        User $actor,
        TenantContext $context,
        Expense $target,
        int $expectedLockVersion,
        ?ExpenseClosureOutcome $outcome,
        string $correlationId,
    ): Expense {
        $this->expensePolicy($context)->update($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $outcome, $target, $tenant): Expense {
            $expense = Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()
                ->find($target->getKey());

            if (! $expense instanceof Expense || (int) $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }

            $expense->forceFill([
                'state' => ExpenseState::Closed,
                'closure_outcome' => $outcome,
                'closed_at' => CarbonImmutable::now('UTC'),
                'closed_by_user_id' => $actor->getKey(),
                'lock_version' => $expense->lock_version + 1,
            ])->save();

            $this->revisions($actor, $context, RevisionOperation::Update, $correlationId, $expense, [$expense]);
            $this->audit('expense.closed', $correlationId, $actor, $tenant, $expense, ['outcome' => $outcome?->value]);

            return $expense->fresh(['rows']);
        });
    }
}
