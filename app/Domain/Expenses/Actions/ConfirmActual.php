<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ActualConfirmationState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmActual
{
    use ManagesExpenseAggregate;

    public function execute(User $actor, TenantContext $context, Expense $target, ExpenseRow $targetRow, int $expectedLockVersion, string $correlationId): ExpenseRow
    {
        $this->expensePolicy($context)->confirmActual($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContext($actor, $context);
        return DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $target, $targetRow, $tenant): ExpenseRow {
            $expense = Expense::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            $row = ExpenseRow::query()->where('tenant_id', $tenant->getKey())->where('expense_id', $expense?->getKey())->lockForUpdate()->find($targetRow->getKey());
            if (! $expense instanceof Expense || ! $row instanceof ExpenseRow || $row->type !== ExpenseType::Actual || $row->confirmation_state !== ActualConfirmationState::ToConfirm || $row->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $row->fill(['confirmation_state' => ActualConfirmationState::Confirmed, 'confirmed_by_user_id' => $actor->getKey(), 'confirmed_at' => CarbonImmutable::now('UTC'), 'is_system_managed' => false, 'lock_version' => $row->lock_version + 1])->save();
            $this->revisions($actor, $context, RevisionOperation::Update, $correlationId, $expense, [$row]);
            $this->audit('expense.actual-confirmed', $correlationId, $actor, $tenant, $expense, ['row_id' => $row->getKey()]);
            return $row->fresh();
        });
    }
}
