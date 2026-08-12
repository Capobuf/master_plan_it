<?php

namespace App\Domain\Plafonds\Actions;

use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use App\Domain\Plafonds\Data\AllocationAdjustmentData;
use App\Domain\Plafonds\Data\PlafondAggregateMapper;
use App\Domain\Plafonds\Services\PlafondRelationshipAuthorizer;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AddAllocationAdjustment
{
    use ManagesExpenseAggregate;

    public function execute(
        User $actor,
        TenantContext $context,
        Expense $target,
        int $expectedLockVersion,
        AllocationAdjustmentData $adjustment,
        string $correlationId,
    ): Expense {
        $this->expensePolicy($context)->update($actor, $target)->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
        [$actor, $tenant] = $this->persistedContext($actor, $context);

        return DB::transaction(function () use (
            $actor,
            $adjustment,
            $context,
            $correlationId,
            $expectedLockVersion,
            $target,
            $tenant,
        ): Expense {
            $planningYearId = (int) $target->planning_year_id;
            $years = app(AnnualEconomicMutationGuard::class)->acquire(
                (int) $tenant->getKey(),
                [$planningYearId],
            );
            app(PlafondLifecycleGuard::class)->assertPreparation(
                $years->get($planningYearId)->budget_state,
            );
            $expense = Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->first();
            if (! $expense instanceof Expense || $expense->lock_version !== $expectedLockVersion) {
                throw new DomainException('STALE_VERSION');
            }
            if ($expense->kind !== ExpenseKind::Plafond) {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $current = $this->annualProjection($actor, $context, $planningYearId);
            $rows = $expense->rows()
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->map(fn (ExpenseRow $row) => PlafondAggregateMapper::fromPersisted($row))
                ->values()
                ->all();
            $rows[] = $adjustment->toExpenseRow(count($rows) + 1);
            $save = PlafondAggregateMapper::header($expense, $expectedLockVersion);
            $changed = $this->saveAggregate($expense, $tenant, $save, $rows, $actor);
            $proposed = $this->annualProjection($actor, $context, $planningYearId);
            $this->assertPlafondAggregateHasCapacity(
                $current,
                $proposed,
                (int) $expense->getKey(),
                'adjustment.entered_amount',
            );
            $this->revisions(
                $actor,
                $context,
                RevisionOperation::Update,
                $correlationId,
                $expense,
                $changed,
            );
            $this->audit(
                'expense.plafond.allocation-adjusted',
                $correlationId,
                $actor,
                $tenant,
                $expense,
                ['adjustments' => count($rows)],
            );

            return $expense->fresh(['rows']);
        });
    }
}
