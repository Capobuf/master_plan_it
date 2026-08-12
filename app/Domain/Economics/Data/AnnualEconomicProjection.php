<?php

namespace App\Domain\Economics\Data;

final readonly class AnnualEconomicProjection
{
    /**
     * @param  array<int, ExpenseEconomicProjection>  $expenses
     * @param  list<ProjectedEconomicLine>  $lines
     * @param  array<int, PlafondEconomicProjection>  $plafonds
     */
    public function __construct(public int $tenantId, public int $planningYearId, public int $economicYearLabel, public string $currency, public string $basis, public EconomicMeasure $currentPlanning, public EconomicMeasure $actual, public array $expenses, public array $lines, public array $plafonds = []) {}
}
