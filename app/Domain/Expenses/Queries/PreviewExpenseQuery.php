<?php

namespace App\Domain\Expenses\Queries;

use App\Domain\Expenses\Actions\Concerns\ManagesExpenseAggregate;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Services\ExpenseRelationshipAuthorizer;
use App\Domain\Expenses\Services\PrepareExpenseAggregate;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\User;
use DomainException;
use Illuminate\Validation\ValidationException;

final class PreviewExpenseQuery
{
    use ManagesExpenseAggregate;

    /**
     * @param  list<SaveExpenseRowData>  $rows
     * @param  list<array{id: int, lock_version: int}>  $deletedRows
     * @return array<string, mixed>
     */
    public function execute(
        User $actor,
        TenantContext $context,
        SaveExpenseData $data,
        array $rows,
        ?Expense $target = null,
        array $deletedRows = [],
    ): array {
        if ($target instanceof Expense) {
            $this->expensePolicy($context)->update($actor, $target)->authorize();
        } else {
            $this->expensePolicy($context)->create($actor)->authorize();
        }
        app(ExpenseRelationshipAuthorizer::class)->authorize(
            $actor,
            $context,
            $data->projectId !== null,
            $data->contractId !== null,
        );
        [, $tenant] = $this->persistedContext($actor, $context);

        if ($target instanceof Expense && (int) $target->planning_year_id !== $data->planningYearId) {
            throw new DomainException('TENANT_RELATION_MISMATCH');
        }
        if (! TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)
            ->whereKey($data->planningYearId)
            ->where('active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'planning_year_id' => 'The selected planning year is invalid.',
            ]);
        }

        return app(PrepareExpenseAggregate::class)->prepare(
            $tenant,
            $data,
            $rows,
            $target,
            $deletedRows,
        );
    }
}
