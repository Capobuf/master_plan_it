<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Data\BudgetApprovalPreview;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Budget\Services\BudgetSourceAccessResolver;
use App\Domain\Budget\Services\CoherentBudgetRead;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class BudgetApprovalPreviewQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
        private BudgetProposalComposer $composer,
        private TenantAbilityAuthorizer $authorizer,
        private CoherentBudgetRead $coherentRead,
        private BudgetSourceAccessResolver $sourceAccessResolver,
    ) {}

    public function execute(User $actor, TenantContext $context, int $planningYearId): BudgetApprovalPreview
    {
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'budget.view');
        $authorizedContext = new TenantContext($persistedTenant, $persistedActor);
        $sourceAccess = $this->sourceAccessResolver->resolve($persistedActor, $authorizedContext);
        $canManage = $sourceAccess->expenseUpdate;

        return $this->coherentRead->execute(function () use ($persistedActor, $authorizedContext, $planningYearId, $sourceAccess, $canManage): BudgetApprovalPreview {
            $year = TenantOwnedRecordQuery::forTenant($authorizedContext, PlanningYear::class)->find($planningYearId);
            if (! $year instanceof PlanningYear) {
                throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
            }
            /** @var AnnualEconomicProjection $projection */
            $projection = $this->engine->project($this->datasetQuery->execute($persistedActor, $authorizedContext, $planningYearId));
            $proposal = $this->composer->compose($projection, (int) $year->lock_version, $sourceAccess);
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
        });
    }
}
