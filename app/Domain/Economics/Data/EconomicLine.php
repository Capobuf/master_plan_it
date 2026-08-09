<?php

namespace App\Domain\Economics\Data;

final readonly class EconomicLine
{
    public function __construct(
        public int $expenseId, public int $rowId, public string $expenseKind, public string $type,
        public ?string $confirmationState, public int $costCenterId, public string $costCenterName,
        public string $net, public string $vat, public string $gross, public ?int $fundedPlafondExpenseId,
        public ?string $spendDate, public ?string $periodStart, public ?string $periodEnd, public ?string $distribution,
        public bool $isExtra = false,
        public ?int $projectId = null,
        public ?string $projectStage = null,
    ) {}
}
