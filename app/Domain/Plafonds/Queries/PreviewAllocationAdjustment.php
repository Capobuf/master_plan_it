<?php

namespace App\Domain\Plafonds\Queries;

use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Economics\Services\MoneyCalculator;
use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Services\ExpenseAggregateValidator;
use App\Domain\Expenses\Services\PlafondCapacityService;
use App\Domain\Expenses\Services\PlafondLifecycleGuard;
use App\Domain\Expenses\Services\ProposedExpenseProjection;
use App\Domain\Plafonds\Data\AllocationAdjustmentData;
use App\Domain\Plafonds\Data\PlafondAggregateMapper;
use App\Domain\Plafonds\Services\PlafondRelationshipAuthorizer;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\User;
use DomainException;

final class PreviewAllocationAdjustment
{
    use ManagesExpenseAggregate;

    public function execute(
        User $actor,
        TenantContext $context,
        Expense $target,
        int $expectedLockVersion,
        AllocationAdjustmentData $adjustment,
    ): PlafondImpact {
        $this->expensePolicy($context)->update($actor, $target)->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
        [$actor, $tenant] = $this->persistedContext($actor, $context);
        $expense = TenantOwnedRecordQuery::forTenant($context, Expense::class)
            ->whereKey($target->getKey())
            ->first();
        if (! $expense instanceof Expense || $expense->lock_version !== $expectedLockVersion) {
            throw new DomainException('STALE_VERSION');
        }
        if ($expense->kind !== ExpenseKind::Plafond) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->whereKey($expense->planning_year_id)
            ->first();
        if (! $year instanceof PlanningYear) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
        app(PlafondLifecycleGuard::class)->assertPreparation($year->budget_state);

        $rows = $expense->rows()
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseRow $row) => PlafondAggregateMapper::fromPersisted($row))
            ->values()
            ->all();
        $rows[] = $adjustment->toExpenseRow(count($rows) + 1);
        $save = PlafondAggregateMapper::header($expense, $expectedLockVersion);
        $validated = app(ExpenseAggregateValidator::class)->validate(
            $tenant,
            $save,
            $rows,
            $expense,
        );
        $current = $this->annualProjection($actor, $context, (int) $expense->planning_year_id)
            ->plafonds[(int) $expense->getKey()] ?? null;
        $proposed = app(ProposedExpenseProjection::class)->project(
            $actor,
            $context,
            $tenant,
            $save,
            $validated,
            $expense,
        )->plafonds[(int) $expense->getKey()] ?? null;
        if ($current === null || $proposed === null) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }
        $requested = (new MoneyCalculator)->subtract(
            $proposed->allocation->official,
            $current->allocation->official,
        );

        return app(PlafondCapacityService::class)->impact($current, $proposed, $requested);
    }
}
