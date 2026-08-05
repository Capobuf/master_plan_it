<?php

namespace App\Http\Controllers\Operational;

use App\Domain\MasterData\Actions\CreatePlanningYear;
use App\Domain\MasterData\Actions\DeactivatePlanningYear;
use App\Domain\MasterData\Actions\ReactivatePlanningYear;
use App\Domain\MasterData\Data\CreatePlanningYearData;
use App\Domain\MasterData\Queries\PlanningYearListQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\PlanningYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlanningYearController extends Controller
{
    public function __construct(private readonly AuthorizeApplicationAbility $authorizeAbility) {}

    public function index(Request $request, PlanningYearListQuery $planningYears): Response
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);

        return Inertia::render('Operational/PlanningYears/Index', [
            'planningYears' => $planningYears->forTenant($actor, $context)
                ->get()
                ->map(fn (PlanningYear $year): array => $this->planningYearProps($year))
                ->all(),
            'abilities' => [
                'create' => $this->authorizeAbility->allows($request, $actor, 'planning-year.create'),
                'deactivate' => $this->authorizeAbility->allows($request, $actor, 'planning-year.deactivate'),
                'reactivate' => $this->authorizeAbility->allows($request, $actor, 'planning-year.reactivate'),
            ],
        ]);
    }

    public function store(Request $request, CreatePlanningYear $createPlanningYear): RedirectResponse
    {
        $validated = $request->validate([
            'year_label' => ['required', 'integer', 'between:1000,9999'],
        ]);

        $createPlanningYear->execute(
            $this->actor($request),
            $this->tenantContext($request),
            new CreatePlanningYearData((int) $validated['year_label']),
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.planning-years.index')
            ->with('success', 'Planning year created.');
    }

    public function deactivate(
        Request $request,
        int $planningYear,
        DeactivatePlanningYear $deactivatePlanningYear,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $deactivatePlanningYear->execute(
            $this->actor($request),
            $context,
            $this->planningYear($context, $planningYear),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.planning-years.index')
            ->with('success', 'Planning year deactivated.');
    }

    public function reactivate(
        Request $request,
        int $planningYear,
        ReactivatePlanningYear $reactivatePlanningYear,
    ): RedirectResponse {
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $this->tenantContext($request);

        $reactivatePlanningYear->execute(
            $this->actor($request),
            $context,
            $this->planningYear($context, $planningYear),
            (int) $validated['lock_version'],
            $this->correlationId($request),
        );

        return redirect()
            ->route('operational.planning-years.index')
            ->with('success', 'Planning year reactivated.');
    }

    private function planningYear(TenantContext $context, int $id): PlanningYear
    {
        /** @var PlanningYear $planningYear */
        $planningYear = TenantOwnedRecordQuery::findOrFail($context, PlanningYear::class, $id);

        return $planningYear;
    }

    /** @return array{id: int, label: int, startsAt: string, endsAt: string, isActive: bool, lockVersion: int} */
    private function planningYearProps(PlanningYear $planningYear): array
    {
        $data = new CreatePlanningYearData((int) $planningYear->year_label);

        return [
            'id' => (int) $planningYear->getKey(),
            'label' => (int) $planningYear->year_label,
            'startsAt' => $data->startDate(),
            'endsAt' => $data->endDate(),
            'isActive' => (bool) $planningYear->active,
            'lockVersion' => (int) $planningYear->lock_version,
        ];
    }
}
