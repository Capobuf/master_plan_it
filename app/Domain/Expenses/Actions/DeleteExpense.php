<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Contracts\Actions\SuppressContractOccurrence;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
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
            $expense = Expense::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->find($target->getKey());
            if (! $expense instanceof Expense || $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            $sourceKeys = $expense->rows()->whereNotNull('source_key')->pluck('source_key')->all();
            if ($suppressOccurrence) {
                foreach ($sourceKeys as $sourceKey) {
                    app(SuppressContractOccurrence::class)->execute($actor, $context, (int) $expense->contract_id, (string) $sourceKey, null, $correlationId);
                }
            }
            $deletedRows=$expense->rows()->get();
            foreach($deletedRows as $deletedRow){$deletedRow->lock_version++;$deletedRow->save();$deletedRow->delete();}
            $expense->lock_version++;$expense->save();
            $this->revisions($actor, $context, RevisionOperation::Delete, $correlationId, $expense, [$expense,...$deletedRows->all()]);
            $this->audit('expense.deleted', $correlationId, $actor, $tenant, $expense, ['suppressed' => $suppressOccurrence]);
            $expense->delete();
        });
    }
}
