<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Attachments\Actions\PurgeAttachments;
use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Contracts\Actions\SuppressContractOccurrence;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeleteExpense
{
    use ManagesExpenseAggregate;

    public function execute(User $actor, TenantContext $context, Expense $target, int $expectedLockVersion, bool $suppressOccurrence, string $correlationId): void
    {
        $this->expensePolicy($context)->delete($actor, $target)->authorize();
        [$actor, $tenant] = $this->persistedContext($actor, $context);
        DB::transaction(function () use ($actor, $context, $correlationId, $expectedLockVersion, $suppressOccurrence, $target, $tenant): void {
            $years = app(AnnualEconomicMutationGuard::class)->acquire(
                (int) $tenant->getKey(),
                [(int) $target->planning_year_id],
            );
            $expense = Expense::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $expense instanceof Expense || $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $isPlafond = $expense->kind === ExpenseKind::Plafond;
            $usesPlafond = $this->currentFundingPlafondIds($expense) !== [];
            if ($isPlafond || $usesPlafond) {
                app(PlafondLifecycleGuard::class)->assertPreparation(
                    $years->get((int) $expense->planning_year_id)->budget_state,
                );
            }
            if ($isPlafond && ExpenseRow::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('funded_plafond_expense_id', $expense->getKey())
                ->exists()) {
                throw new DomainException('REFERENCED_RECORD_DELETE_DENIED');
            }
            $sourceKeys = $expense->rows()->whereNotNull('source_key')->pluck('source_key')->all();
            if ($suppressOccurrence) {
                foreach ($sourceKeys as $sourceKey) {
                    app(SuppressContractOccurrence::class)->execute($actor, $context, (int) $expense->contract_id, (string) $sourceKey, null, $correlationId);
                }
            }
            $deletedRows = $expense->rows()->orderBy('id')->lockForUpdate()->get();
            foreach ($deletedRows as $deletedRow) {
                $deletedRow->lock_version++;
                $deletedRow->save();
                $deletedRow->delete();
            }
            $expense->lock_version++;
            $expense->save();
            $this->revisions($actor, $context, RevisionOperation::Delete, $correlationId, $expense, [$expense, ...$deletedRows->all()]);
            $this->audit('expense.deleted', $correlationId, $actor, $tenant, $expense, ['suppressed' => $suppressOccurrence]);
            $expense->delete();
            app(PurgeAttachments::class)->forExpenseAggregate($actor, $context, $expense, $correlationId);
        });
    }
}
