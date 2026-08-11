<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnualBudgetResource;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use Illuminate\Http\Request;

final class CurrentBudgetController extends Controller
{
    public function __invoke(Request $request, AnnualBudgetQuery $budget, HistoricalAnnualBudgetQuery $history, ActivateAnnualHistory $activate): AnnualBudgetResource
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $costCenterId = $this->costCenterId($request, $context);
        /** @var PlanningYear $year */
        $year = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $planningYearId);
        $activate->execute($this->actor($request), $context, $year, $this->correlationId($request));
        $asOf = $request->query('as_of');
        if ($asOf !== null && (! is_string($asOf) || trim($asOf) === '' || mb_strlen($asOf) > 40)) {
            abort(422);
        }

        return AnnualBudgetResource::make($asOf === null
            ? $budget->execute($this->actor($request), $context, $planningYearId, $costCenterId)
            : $history->execute($this->actor($request), $context, $planningYearId, $asOf, $costCenterId));
    }

    private function planningYearId(Request $request): int
    {
        $value = $request->query('planning_year_id', $request->query('year'));

        if (! is_numeric($value) || (int) $value < 1) {
            abort(404);
        }

        return (int) $value;
    }

    private function costCenterId(Request $request, TenantContext $context): ?int
    {
        $value = $request->query('cost_center', $request->query('cost_center_id'));

        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value < 1 || ! TenantOwnedRecordQuery::forTenant($context, CostCenter::class)->whereKey((int) $value)->exists()) {
            abort(404);
        }

        return (int) $value;
    }
}
