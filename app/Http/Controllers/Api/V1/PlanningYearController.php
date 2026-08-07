<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\MasterData\Actions\CreatePlanningYear;
use App\Domain\MasterData\Actions\DeactivatePlanningYear;
use App\Domain\MasterData\Actions\ReactivatePlanningYear;
use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Domain\MasterData\Queries\PlanningYearListQuery;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlanningYearResource;
use App\Models\PlanningYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

final class PlanningYearController extends Controller
{
    public function index(Request $request, PlanningYearListQuery $planningYears): AnonymousResourceCollection
    {
        $query = $planningYears->forTenant($this->actor($request), $this->tenantContext($request));

        return PlanningYearResource::collection(
            $query->paginate(min(max($request->integer('per_page', 15), 1), 100))->withQueryString(),
        );
    }

    public function store(Request $request, CreatePlanningYear $createPlanningYear): PlanningYearResource
    {
        $this->rejectUnexpectedFields($request, ['year_label']);
        $validated = $request->validate(['year_label' => ['required', 'integer', 'between:1000,9999']]);
        $planningYear = $createPlanningYear->execute(
            $this->actor($request),
            $this->tenantContext($request),
            new CreatePlanningYearData((int) $validated['year_label']),
            $this->correlationId($request),
        );

        return PlanningYearResource::make($planningYear);
    }

    public function deactivate(Request $request, int $planningYear, DeactivatePlanningYear $deactivatePlanningYear): PlanningYearResource
    {
        $validated = $this->lifecycleInput($request);
        $updated = $deactivatePlanningYear->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $this->planningYear($request, $planningYear),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return PlanningYearResource::make($updated);
    }

    public function reactivate(Request $request, int $planningYear, ReactivatePlanningYear $reactivatePlanningYear): PlanningYearResource
    {
        $validated = $this->lifecycleInput($request);
        $updated = $reactivatePlanningYear->execute(
            $this->actor($request),
            $this->tenantContext($request),
            $this->planningYear($request, $planningYear),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return PlanningYearResource::make($updated);
    }

    private function planningYear(Request $request, int $id): PlanningYear
    {
        /** @var PlanningYear $planningYear */
        $planningYear = TenantOwnedRecordQuery::findOrFail($this->tenantContext($request), PlanningYear::class, $id);

        return $planningYear;
    }

    /** @return array{lock_version: int} */
    private function lifecycleInput(Request $request): array
    {
        $this->rejectUnexpectedFields($request, ['lock_version']);

        /** @var array{lock_version: int} $validated */
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);

        return $validated;
    }

    /** @param list<string> $allowed */
    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(array_fill_keys($unexpected, 'This field is not allowed for this operation.'));
        }
    }
}
