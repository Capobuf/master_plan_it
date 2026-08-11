<?php

namespace App\Domain\Reporting\Data;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicSummary;

final readonly class EconomicReportData
{
    /**
     * @param  array{summary: EconomicSummary, monthly: array<string, string>, byType: array<string, string>, byCostCenter: array<string, string>}  $calculated
     * @param  list<EconomicLine>  $lines
     */
    public function __construct(
        public EconomicDataset $dataset,
        public array $calculated,
        public array $lines,
        public int $page,
        public int $perPage,
        public int $total,
        public ?int $costCenterId,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
