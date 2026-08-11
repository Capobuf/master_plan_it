<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Reporting\Queries\AnnualEconomicReportQuery;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EconomicReportController extends Controller
{
    public function __invoke(Request $request, AnnualEconomicReportQuery $report, ActivateAnnualHistory $activate): JsonResponse
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $costCenterId = $this->tenantOwnedId($request, $context, CostCenter::class, 'cost_center_id', 'cost_center');
        $projectId = $this->tenantOwnedId($request, $context, Project::class, 'project_id');
        $vendorId = $this->tenantOwnedId($request, $context, Vendor::class, 'vendor_id');
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $groupBy = (string) $request->query('group_by', 'cost_center');
        $state = $request->query('state');
        if ($state !== null && (! is_string($state) || ! in_array($state, ['open', 'closed'], true))) {
            abort(422);
        }
        /** @var PlanningYear $year */
        $year = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $planningYearId);
        $activate->execute($this->actor($request), $context, $year, $this->correlationId($request));
        $asOf = $request->query('as_of');
        if ($asOf !== null && (! is_string($asOf) || trim($asOf) === '' || mb_strlen($asOf) > 40)) {
            abort(422);
        }
        $result = $report->execute(
            $this->actor($request),
            $context,
            new EconomicReportFilterData(
                $planningYearId,
                $costCenterId,
                $projectId,
                $vendorId,
                $state,
                $groupBy,
                max(1, $request->integer('page', 1)),
                $perPage,
                $asOf,
            ),
        );

        return response()->json($result);
    }

    private function planningYearId(Request $request): int
    {
        $value = $request->query('planning_year_id', $request->query('year'));

        if (! is_numeric($value) || (int) $value < 1) {
            abort(404);
        }

        return (int) $value;
    }

    /** @param class-string<Model> $model */
    private function tenantOwnedId(
        Request $request,
        TenantContext $context,
        string $model,
        string $parameter,
        ?string $alias = null,
    ): ?int {
        $value = $request->query($parameter, $alias === null ? null : $request->query($alias));

        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (int) $value < 1 || ! TenantOwnedRecordQuery::forTenant($context, $model)->whereKey((int) $value)->exists()) {
            abort(404);
        }

        return (int) $value;
    }
}
