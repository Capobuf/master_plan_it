<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportingDatasetResource;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, EconomicDatasetQuery $datasetQuery, EconomicEngine $engine): ReportingDatasetResource
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $dataset = $datasetQuery->execute($this->actor($request), $context, $planningYearId);

        return ReportingDatasetResource::make([
            'dataset' => $dataset,
            'calculated' => $engine->calculate($dataset),
            'cost_center_id' => null,
        ]);
    }

    private function planningYearId(Request $request): int
    {
        $value = $request->query('planning_year_id', $request->query('year'));

        if (! is_numeric($value) || (int) $value < 1) {
            abort(404);
        }

        return (int) $value;
    }
}
