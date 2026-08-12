<?php

namespace App\Domain\Economics\Data;

final readonly class PlafondEconomicProjection
{
    /**
     * @param  list<ProjectedEconomicLine>  $allocationLines
     * @param  list<ProjectedEconomicLine>  $coveredLines
     */
    public function __construct(
        public int $plafondExpenseId,
        public int $planningYearId,
        public string $title,
        public int $costCenterId,
        public string $costCenterName,
        public string $currency,
        public string $basis,
        public EconomicMeasure $allocation,
        public EconomicMeasure $coveragePlanned,
        public EconomicMeasure $consumed,
        public EconomicMeasure $available,
        public array $allocationLines,
        public array $coveredLines,
    ) {}
}
