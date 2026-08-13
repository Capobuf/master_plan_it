<?php

namespace App\Domain\Reporting\Data;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use Carbon\CarbonImmutable;

final readonly class AnnualReportEvidence
{
    /**
     * @param  array<string, array<int, string>>  $labels
     */
    public function __construct(
        public AnnualEconomicProjection $projection,
        public int $planningYearId,
        public int $yearLabel,
        public string $state,
        public int $lockVersion,
        public ?string $warning,
        public ?CarbonImmutable $historyActivatedAt,
        public array $labels,
        public ?CarbonImmutable $cutoff,
    ) {}
}
