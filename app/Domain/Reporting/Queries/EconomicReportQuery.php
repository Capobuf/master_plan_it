<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Data\EconomicReportData;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;

final readonly class EconomicReportQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        int $planningYearId,
        ?int $costCenterId,
        int $page,
        int $perPage = 25,
    ): EconomicReportData {
        $dataset = $this->datasetQuery->execute(
            $actor,
            $context,
            $planningYearId,
            new EconomicReportFilterData($planningYearId, $costCenterId),
        );
        $total = count($dataset->lines);
        $page = min(max(1, $page), max(1, (int) ceil($total / $perPage)));

        return new EconomicReportData(
            $dataset,
            $this->engine->calculate($dataset),
            array_slice($dataset->lines, ($page - 1) * $perPage, $perPage),
            $page,
            $perPage,
            $total,
            $costCenterId,
        );
    }
}
