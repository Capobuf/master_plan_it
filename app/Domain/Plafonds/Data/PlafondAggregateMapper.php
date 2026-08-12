<?php

namespace App\Domain\Plafonds\Data;

use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseRow;

final class PlafondAggregateMapper
{
    public static function header(Expense $expense, int $expectedLockVersion): SaveExpenseData
    {
        return new SaveExpenseData(
            planningYearId: (int) $expense->planning_year_id,
            costCenterId: (int) $expense->cost_center_id,
            kind: ExpenseKind::Plafond,
            title: (string) $expense->title,
            notes: $expense->notes,
            projectId: null,
            contractId: null,
            expectedLockVersion: $expectedLockVersion,
        );
    }

    public static function fromPersisted(ExpenseRow $row): SaveExpenseRowData
    {
        return new SaveExpenseRowData(
            id: (int) $row->getKey(),
            position: (int) $row->position,
            vendorId: null,
            type: ExpenseType::from((string) $row->getRawOriginal('type')),
            description: (string) $row->description,
            quantity: $row->quantity === null ? null : (string) $row->quantity,
            unitPrice: $row->unit_price === null ? null : (string) $row->unit_price,
            enteredAmount: $row->entered_amount === null ? null : (string) $row->entered_amount,
            amountIncludesVat: (bool) $row->amount_includes_vat,
            vatRate: (string) $row->vat_rate,
            isExtra: false,
            fundedPlafondExpenseId: null,
            spendDate: $row->getRawOriginal('spend_date') === null
                ? null
                : (string) $row->getRawOriginal('spend_date'),
            periodStart: null,
            periodEnd: null,
            distribution: null,
            externalReference: null,
            expectedLockVersion: (int) $row->lock_version,
            isCurrentPlanning: false,
            notes: $row->notes,
            createdByUserId: $row->created_by_user_id === null ? null : (int) $row->created_by_user_id,
        );
    }
}
