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
        public string $state,
        public string $title,
        public ?int $projectId,
        public ?string $projectTitle,
        public bool $projectCurrent,
        public ?int $contractId,
        public ?string $contractTitle,
        public bool $contractCurrent,
        public int $vendorCount,
        public string $vendorSummary,
        public int $rowCount,
        public int $lockVersion,
        public string $netTotal,
        public string $vatTotal,
        public string $grossTotal,
    ) {}
}
