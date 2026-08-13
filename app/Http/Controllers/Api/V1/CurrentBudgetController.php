<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnualBudgetResource;
use Illuminate\Http\Request;

final class CurrentBudgetController extends Controller
{
    public function __invoke(Request $request, AnnualBudgetQuery $budget, HistoricalAnnualBudgetQuery $history): AnnualBudgetResource
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $unknown = array_diff(array_keys($request->query()), ['planning_year_id', 'as_of']);
        if ($unknown !== []) {
            abort(422);
        }
        $asOf = $request->query('as_of');
        if ($asOf !== null && (! is_string($asOf) || trim($asOf) === '' || mb_strlen($asOf) > 40)) {
            abort(422);
        }

        return AnnualBudgetResource::make($asOf === null
            ? $budget->execute($this->actor($request), $context, $planningYearId)
            : $history->execute($this->actor($request), $context, $planningYearId, $asOf));
    }

    private function planningYearId(Request $request): int
    {
        if ($request->query('year') !== null) {
            abort(422);
        }
        $value = $request->query('planning_year_id');

        if (! is_numeric($value) || (int) $value < 1) {
            abort(404);
        }

        return (int) $value;
    }
}
