<?php

namespace App\Domain\Reporting\Data;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicSummary;

final readonly class CurrentBudgetData
{
    /**
     * @param  array{summary: EconomicSummary, monthly: array<string, string>, byType: array<string, string>, byCostCenter: array<string, string>}  $calculated
     */
    public function __construct(
        public EconomicDataset $dataset,
        public array $calculated,
        public ?int $costCenterId,
    ) {}
}
