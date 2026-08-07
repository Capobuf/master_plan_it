<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\Queries\CurrentBudgetQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportingDatasetResource;
use App\Models\CostCenter;
use Illuminate\Http\Request;

final class CurrentBudgetController extends Controller
{
    public function __invoke(Request $request, CurrentBudgetQuery $budget): ReportingDatasetResource
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $costCenterId = $this->costCenterId($request, $context->tenantId);
        $result = $budget->execute($this->actor($request), $context, $planningYearId, $costCenterId);

        return ReportingDatasetResource::make([
            'dataset' => $result->dataset,
            'calculated' => $result->calculated,
            'cost_center_id' => $result->costCenterId,
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

    private function costCenterId(Request $request, int $tenantId): ?int
    {
        $value = $request->query('cost_center', $request->query('cost_center_id'));

        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value < 1 || ! CostCenter::query()->where('tenant_id', $tenantId)->whereKey((int) $value)->exists()) {
            abort(404);
        }

        return (int) $value;
    }
}
