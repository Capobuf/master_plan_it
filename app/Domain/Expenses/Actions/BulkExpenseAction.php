<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Contracts\Actions\DeleteGeneratedExpense;
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
        private readonly DeleteExpense $deleteExpense,
        private readonly DeleteGeneratedExpense $deleteGeneratedExpense,
    ) {}

    /**
     * @param  list<array{id: int, lock_version: int}>  $items
     * @return array{action: string, affected_count: int, destinations: list<never>}
     */
    public function execute(
        User $actor,
        TenantContext $context,
        string $action,
        int $planningYearId,
        array $items,
        string $correlationId,
        ?bool $allowRegeneration = null,
    ): array {
        if ($action !== 'delete' || $allowRegeneration === null) {
            throw new DomainException('EXPENSE_BULK_ITEM_NOT_APPLICABLE');
        }

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
            $actor, $allowRegeneration, $context, $correlationId, $expenses, $items, $versions,
        ): array {
            foreach ($expenses as $expense) {
                $expectedVersion = (int) $versions->get((int) $expense->getKey());
                $itemCorrelationId = Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    $correlationId.':delete:'.$expense->getKey(),
                )->toString();
                $generated = $expense->rows()->whereNotNull('source_key')->exists();

                if ($generated) {
                    $this->deleteGeneratedExpense->execute(
                        $actor,
                        $context,
                        $expense,
                        $expectedVersion,
                        $allowRegeneration,
                        $itemCorrelationId,
                    );
                } else {
                    $this->deleteExpense->execute(
                        $actor,
                        $context,
                        $expense,
                        $expectedVersion,
                        false,
                        $itemCorrelationId,
                    );
                }
            }

            return ['action' => 'delete', 'affected_count' => count($items), 'destinations' => []];
        });
    }
}
