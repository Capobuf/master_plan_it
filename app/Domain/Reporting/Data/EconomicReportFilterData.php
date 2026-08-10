<?php

namespace App\Domain\Reporting\Data;

final readonly class EconomicReportFilterData
{
    public function __construct(
        public int $planningYearId,
        public ?int $costCenterId = null,
        public ?int $projectId = null,
        public ?int $vendorId = null,
        public ?string $state = null,
        public string $groupBy = 'cost_center',
        public int $page = 1,
        public int $perPage = 25,
        public ?string $asOf = null,
    ) {}
}
