<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\Queries\TenantDashboardQuery;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportingDatasetResource;
use App\Models\PlanningYear;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantDashboardQuery $dashboard): ReportingDatasetResource
    {
        $value = $request->query('planning_year_id');
        if ($request->query('year') !== null) {
            abort(422);
        }
        if (! is_numeric($value) || (int) $value < 1) {
            abort(404);
        }

        $context = $this->tenantContext($request);
        /** @var PlanningYear $year */
        $year = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, (int) $value);

        return ReportingDatasetResource::make(
            $dashboard->apiDataset($this->actor($request), $context, (int) $year->getKey()),
        );
    }
}
