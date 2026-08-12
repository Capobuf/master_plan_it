<?php

namespace App\Domain\Economics\Data;

final readonly class ProjectedEconomicLine
{
    public function __construct(
        public int $expenseId, public int $rowId, public int $planningYearId, public int $economicYearLabel,
        public string $type, public bool $isCurrentPlanning, public bool $contributesToCurrentPlanning,
        public ?string $spendDate, public EconomicMeasure $amount,
        public string $description = '', public ?string $notes = null,
        public ?int $costCenterId = null, public ?int $vendorId = null, public ?string $vendorName = null,
        public ?int $projectId = null, public ?int $contractId = null,
    ) {}
}
