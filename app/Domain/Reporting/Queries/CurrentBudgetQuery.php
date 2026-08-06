<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Data\CurrentBudgetData;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;

final readonly class CurrentBudgetQuery
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
    ): CurrentBudgetData {
        $dataset = $this->datasetQuery->execute(
            $actor,
            $context,
            $planningYearId,
            new EconomicReportFilterData($planningYearId, $costCenterId),
        );

        return new CurrentBudgetData($dataset, $this->engine->calculate($dataset), $costCenterId);
    }
}
