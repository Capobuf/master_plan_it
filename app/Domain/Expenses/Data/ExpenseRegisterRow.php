<?php

namespace App\Domain\Expenses\Data;

final readonly class ExpenseRegisterRow
{
    public function __construct(
        public int $id,
        public int $planningYearId,
        public int $planningYearLabel,
        public int $costCenterId,
        public string $costCenterName,
        public string $kind,
        public string $title,
        public ?int $contractId,
        public ?string $contractTitle,
        public bool $contractCurrent,
        public int $rowCount,
        public string $netTotal,
        public string $vatTotal,
        public string $grossTotal,
    ) {}
}
