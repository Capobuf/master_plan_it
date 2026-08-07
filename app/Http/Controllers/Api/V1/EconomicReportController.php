<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\Queries\EconomicReportQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportingLineResource;
use App\Http\Resources\Api\V1\ReportingScopeResource;
use App\Http\Resources\Api\V1\ReportingSummaryResource;
use App\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class EconomicReportController extends Controller
{
    public function __invoke(Request $request, EconomicReportQuery $report): AnonymousResourceCollection
    {
        $context = $this->tenantContext($request);
        $planningYearId = $this->planningYearId($request);
        $costCenterId = $this->costCenterId($request, $context);
        $perPage = min(max($request->integer('per_page', 25), 1), 100);
        $result = $report->execute(
            $this->actor($request),
            $context,
            $planningYearId,
            $costCenterId,
            max(1, $request->integer('page', 1)),
            $perPage,
        );
        $paginator = new LengthAwarePaginator(
            $result->lines,
            $result->total,
            $result->perPage,
            $result->page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ReportingLineResource::collection($paginator)->additional([
            'scope' => ReportingScopeResource::make($result->dataset->scope)->resolve($request),
            'summary' => ReportingSummaryResource::make($result->calculated['summary'])->resolve($request),
            'filters' => [
                'planning_year_id' => $planningYearId,
                'cost_center_id' => $costCenterId,
            ],
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
