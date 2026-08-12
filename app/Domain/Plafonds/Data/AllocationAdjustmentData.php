<?php

namespace App\Domain\Plafonds\Data;

use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseType;

final readonly class AllocationAdjustmentData
{
    public function __construct(
        public string $description,
        public ?string $notes,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?string $enteredAmount,
        public bool $amountIncludesVat,
        public string $vatRate,
        public string $date,
    ) {}

    public function toExpenseRow(int $position): SaveExpenseRowData
    {
        return new SaveExpenseRowData(
            id: null,
            position: $position,
            vendorId: null,
            type: ExpenseType::AllocationAdjustment,
            description: $this->description,
            quantity: $this->quantity,
            unitPrice: $this->unitPrice,
            enteredAmount: $this->enteredAmount,
            amountIncludesVat: $this->amountIncludesVat,
            vatRate: $this->vatRate,
            isExtra: false,
            fundedPlafondExpenseId: null,
            spendDate: $this->date,
            periodStart: null,
            periodEnd: null,
            distribution: null,
            externalReference: null,
            expectedLockVersion: null,
            isCurrentPlanning: false,
            notes: $this->notes,
        );
    }
}
