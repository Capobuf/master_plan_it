<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Data\ApprovalExclusion;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Budget\Services\BudgetSourceAccessResolver;
use App\Domain\Budget\Services\CoherentBudgetRead;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class AnnualBudgetQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
        private BudgetProposalComposer $composer,
        private TenantAbilityAuthorizer $authorizer,
        private CoherentBudgetRead $coherentRead,
        private BudgetSourceAccessResolver $sourceAccessResolver,
    ) {}

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, int $planningYearId): array
    {
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'budget.view');
        $authorizedContext = new TenantContext($persistedTenant, $persistedActor);
        $sourceAccess = $this->sourceAccessResolver->resolve($persistedActor, $authorizedContext);
        $canManage = $sourceAccess->expenseUpdate;

        return $this->coherentRead->execute(function () use ($persistedActor, $authorizedContext, $planningYearId, $sourceAccess, $canManage): array {
            $tenant = Tenant::query()->find($authorizedContext->tenantId);
            $year = TenantOwnedRecordQuery::forTenant($authorizedContext, PlanningYear::class)->find($planningYearId);
            if (! $tenant instanceof Tenant || ! $year instanceof PlanningYear) {
                throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
            }

            /** @var AnnualEconomicProjection $projection */
            $projection = $this->engine->project($this->datasetQuery->execute($persistedActor, $authorizedContext, $planningYearId));
            $proposal = $this->composer->compose($projection, (int) $year->lock_version, $sourceAccess);
            $state = $year->budget_state instanceof BudgetState
                ? $year->budget_state->value
                : (string) $year->budget_state;
            $proposalArray = $proposal->toArray();
            unset($proposalArray['contributors'], $proposalArray['exclusions']);
            $lockedAt = $tenant->economic_basis_locked_at;

            return [
                'planning_year' => [
                    'id' => (int) $year->getKey(),
                    'year_label' => (int) $year->year_label,
                    'state' => $state,
                    'lock_version' => (int) $year->lock_version,
                ],
                'currency' => $projection->currency,
                'basis' => $projection->basis,
                'economic_base' => [
                    'basis' => $projection->basis,
                    'locked_at' => $lockedAt instanceof CarbonInterface ? $lockedAt->toISOString() : null,
                ],
                'proposal' => $proposalArray,
                'approved_snapshot' => null,
                'informative_evaluations' => $this->measure($this->informativeEvaluations($proposal->exclusions, $projection->basis)),
                'actuals' => $this->measure($projection->actual),
                'actions' => [
                    'can_view_approval_preview' => true,
                    'can_approve' => $canManage && $state === BudgetState::Preparation->value && ! $proposal->isEmpty(),
                    'can_annul_active_approval' => false,
                ],
            ];
        });
    }

    /** @param list<ApprovalExclusion> $exclusions */
    private function informativeEvaluations(array $exclusions, string $basis): EconomicMeasure
    {
        $measure = EconomicMeasure::zero($basis);
        foreach ($exclusions as $exclusion) {
            if (! in_array($exclusion->reason, ['alternative_planning', 'covered_by_plafond', 'non_current_planning'], true)) {
                continue;
            }
            $measure = $measure->plus($exclusion->amount, $basis);
        }

        return $measure;
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return [
            'net' => $measure->net,
            'vat' => $measure->vat,
            'gross' => $measure->gross,
            'official' => $measure->official,
        ];
    }
}
