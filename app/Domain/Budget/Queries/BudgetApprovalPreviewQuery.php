<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Data\BudgetApprovalPreview;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\PermissionRegistrar;

final readonly class BudgetApprovalPreviewQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
        private BudgetProposalComposer $composer,
        private PermissionRegistrar $permissionRegistrar,
        private PlatformAdministrator $platformAdministrator,
    ) {}

    public function execute(User $actor, TenantContext $context, int $planningYearId): BudgetApprovalPreview
    {
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }
        /** @var AnnualEconomicProjection $projection */
        $projection = $this->engine->project($this->datasetQuery->execute($actor, $context, $planningYearId));
        $policy = new ExpensePolicy($context, $this->permissionRegistrar, $this->platformAdministrator);
        $canViewSources = $policy->viewAny($actor)->allowed();
        $canManage = $policy->manageBudget($actor)->allowed();
        $proposal = $this->composer->compose(
            $projection,
            (int) $year->lock_version,
            $canViewSources,
        );
        $state = $year->budget_state instanceof BudgetState
            ? $year->budget_state->value
            : (string) $year->budget_state;

        return new BudgetApprovalPreview(
            planningYearId: (int) $year->getKey(),
            yearLabel: (int) $year->year_label,
            state: $state,
            lockVersion: (int) $year->lock_version,
            proposal: $proposal,
            canApprove: $canManage && $state === BudgetState::Preparation->value && ! $proposal->isEmpty(),
        );
    }
}
