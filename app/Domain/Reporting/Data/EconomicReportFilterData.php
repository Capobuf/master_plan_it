<?php

namespace App\Domain\Reporting\Data;

final readonly class EconomicReportFilterData
{
    public function __construct(public ?int $planningYearId) {}
}
