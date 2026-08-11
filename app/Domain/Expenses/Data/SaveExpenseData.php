<?php

namespace App\Domain\Expenses\Data;

use App\Domain\Expenses\Enums\ExpenseKind;

final readonly class SaveExpenseData
{
    public function __construct(
        public int $planningYearId,
        public int $costCenterId,
        public ExpenseKind $kind,
        public string $title,
        public ?string $notes,
        public ?int $projectId,
        public ?int $contractId,
        public ?int $expectedLockVersion,
        public ?int $creditForExpenseId = null,
    ) {}
}
