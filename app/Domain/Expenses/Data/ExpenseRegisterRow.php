<?php

namespace App\Domain\Expenses\Data;

final readonly class ExpenseRegisterRow
{
    /** @param array{current_planning: array<string, string>, actual: array<string, string>} $totals */
    public function __construct(
        public int $id,
        public int $planningYearId,
        public int $economicYearLabel,
        public int $costCenterId,
        public string $costCenterName,
        public string $kind,
        public string $title,
        public ?int $projectId,
        public ?string $projectTitle,
        public bool $projectCurrent,
        public ?int $contractId,
        public ?string $contractTitle,
        public bool $contractCurrent,
        public ?int $currentPlanningRowId,
        public int $vendorCount,
        public string $vendorSummary,
        public int $rowCount,
        public int $lockVersion,
        public string $currency,
        public string $basis,
        public array $totals,
    ) {}
}
