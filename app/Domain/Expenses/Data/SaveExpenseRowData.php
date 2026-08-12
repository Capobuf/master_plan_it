<?php

namespace App\Domain\Expenses\Data;

use App\Domain\Expenses\Enums\Distribution;
use App\Domain\Expenses\Enums\ExpenseType;

final readonly class SaveExpenseRowData
{
    public function __construct(
        public ?int $id,
        public int $position,
        public ?int $vendorId,
        public ExpenseType $type,
        public string $description,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?string $enteredAmount,
        public bool $amountIncludesVat,
        public string $vatRate,
        public bool $isExtra,
        public ?int $fundedPlafondExpenseId,
        public ?string $spendDate,
        public ?string $periodStart,
        public ?string $periodEnd,
        public ?Distribution $distribution,
        public ?string $externalReference,
        public ?int $expectedLockVersion,
        public bool $isCurrentPlanning = false,
        public ?string $notes = null,
        public ?int $createdByUserId = null,
    ) {}
}
