<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class BulkExpenseAction
{
    public function __construct(
        private readonly CloseExpense $closeExpense,
        private readonly MoveExpense $moveExpense,
        private readonly DeleteExpense $deleteExpense,
        private readonly DeleteGeneratedExpense $deleteGeneratedExpense,
    ) {}

    /**
     * @param  list<array{id: int, lock_version: int}>  $items
     * @return array{action: string, affected_count: int, destinations: list<array{origin_expense_id: int, destination_expense_id: int, planning_year_id: int}>}
     */
    public function execute(
        User $actor,
        TenantContext $context,
        string $action,
        int $planningYearId,
        array $items,
        string $correlationId,
        ?ExpenseClosureOutcome $outcome = null,
        ?int $targetPlanningYearId = null,
        ?bool $allowRegeneration = null,
    ): array {
        $versions = collect($items)->mapWithKeys(static fn (array $item): array => [
            $item['id'] => $item['lock_version'],
        ]);
        $expenses = Expense::query()
            ->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $planningYearId)
            ->whereIn('id', $versions->keys())
            ->orderBy('id')
            ->get();

        if ($expenses->count() !== count($items)) {
            throw (new ModelNotFoundException)->setModel(Expense::class);
        }

        return DB::transaction(function () use (
            $action, $actor, $allowRegeneration, $context, $correlationId,
            $expenses, $items, $outcome, $targetPlanningYearId, $versions,
        ): array {
            $destinations = [];

            foreach ($expenses as $expense) {
                $expectedVersion = (int) $versions->get((int) $expense->getKey());
                $itemCorrelationId = $this->itemCorrelationId($correlationId, $action, (int) $expense->getKey());

                if ($action === 'close') {
                    if ($expense->state !== ExpenseState::Open) {
                        throw new DomainException('EXPENSE_BULK_ITEM_NOT_APPLICABLE');
                    }
                    $this->closeExpense->execute($actor, $context, $expense, $expectedVersion, $outcome, $itemCorrelationId);

                    continue;
                }

                if ($action === 'move') {
                    if ($targetPlanningYearId === null) {
                        throw new DomainException('EXPENSE_BULK_ITEM_NOT_APPLICABLE');
                    }
                    [, $destination] = $this->moveExpense->execute(
                        $actor, $context, $expense, $expectedVersion, $targetPlanningYearId, $itemCorrelationId,
                    );
                    $destinations[] = [
                        'origin_expense_id' => (int) $expense->getKey(),
                        'destination_expense_id' => (int) $destination->getKey(),
                        'planning_year_id' => $targetPlanningYearId,
                    ];

                    continue;
                }

                if ($action === 'delete') {
                    if ($allowRegeneration === null) {
                        throw new DomainException('EXPENSE_BULK_ITEM_NOT_APPLICABLE');
                    }
                    $generated = $expense->rows()->whereNotNull('source_key')->exists();
                    if ($generated) {
                        $this->deleteGeneratedExpense->execute(
                            $actor, $context, $expense, $expectedVersion, $allowRegeneration, $itemCorrelationId,
                        );
                    } else {
                        $this->deleteExpense->execute(
                            $actor, $context, $expense, $expectedVersion, false, $itemCorrelationId,
                        );
                    }

                    continue;
                }

                throw new DomainException('EXPENSE_BULK_ITEM_NOT_APPLICABLE');
            }

            return [
                'action' => $action,
                'affected_count' => count($items),
                'destinations' => $destinations,
            ];
        });
    }

    private function itemCorrelationId(string $requestCorrelationId, string $action, int $expenseId): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, $requestCorrelationId.':'.$action.':'.$expenseId)->toString();
    }
}
