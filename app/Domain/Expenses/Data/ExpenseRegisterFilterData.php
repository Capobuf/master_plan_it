<?php

namespace App\Domain\Expenses\Data;

use App\Domain\Expenses\Enums\ExpenseKind;

final readonly class ExpenseRegisterFilterData
{
    public function __construct(
        public int $planningYearId,
        public ?ExpenseKind $kind = null,
        public ?string $query = null,
        public ?int $costCenterId = null,
        public ?int $projectId = null,
        public ?int $contractId = null,
        public ?int $vendorId = null,
    ) {}
}
