<?php

namespace App\Domain\Plafonds\Queries;

use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Expense;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PlafondQuery
{
    public function find(TenantContext $context, int $id, ?int $planningYearId = null): Expense
    {
        $expense = TenantOwnedRecordQuery::forTenant($context, Expense::class)
            ->whereKey($id)
            ->where('kind', ExpenseKind::Plafond->value)
            ->when($planningYearId !== null, fn ($query) => $query->where('planning_year_id', $planningYearId))
            ->first();

        if (! $expense instanceof Expense) {
            throw (new ModelNotFoundException)->setModel(Expense::class, [$id]);
        }

        return $expense;
    }
}
