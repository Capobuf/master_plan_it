<?php

namespace App\Domain\Economics\Data;

final readonly class ExpenseEconomicProjection
{
    /** @param list<ProjectedEconomicLine> $lines */
    public function __construct(public int $expenseId, public ?int $currentPlanningRowId, public EconomicMeasure $currentPlanning, public EconomicMeasure $actual, public array $lines) {}
}
