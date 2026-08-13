<?php

namespace App\Domain\Budget\Actions;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Budget\Data\ApprovalContributor;
use App\Domain\Budget\Data\ApproveBudgetProposalData;
use App\Domain\Budget\Data\BudgetCompositionEvidence;
use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Budget\Services\AnnualEconomicMutationGuard;
use App\Domain\Budget\Services\ApprovalEffectiveDateValidator;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Budget\Services\BudgetSourceAccessResolver;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Queries\EconomicDatasetQuery;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class ApproveBudgetProposal
{
    public function __construct(
        private TenantAbilityAuthorizer $authorizer,
        private AnnualEconomicMutationGuard $guard,
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
        private BudgetProposalComposer $composer,
        private BudgetSourceAccessResolver $sourceAccessResolver,
        private ApprovalEffectiveDateValidator $effectiveDateValidator,
        private BeginRevisionBatch $beginRevisionBatch,
        private LinkVersionToRevisionBatch $linkVersionToRevisionBatch,
        private AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        PlanningYear $target,
        ApproveBudgetProposalData $data,
        string $correlationId,
    ): BudgetApproval {
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'budget.view');
        $this->authorizer->authorize($persistedActor, new TenantContext($persistedTenant, $persistedActor), 'expense.update');
        $targetId = $target->getKey();
        $targetOriginalId = $target->getRawOriginal($target->getKeyName());
        if (! $target->exists || $targetId === null || $targetId !== $targetOriginalId) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$targetId]);
        }

        try {
            return DB::transaction(function () use ($correlationId, $data, $persistedActor, $persistedTenant, $targetId): BudgetApproval {
                $tenantId = (int) $persistedTenant->getKey();
                $actorId = (int) $persistedActor->getKey();
                $years = $this->guard->acquire($tenantId, [(int) $targetId], lockTenant: true);
                $lockedTenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->first();
                $lockedActor = User::query()->whereKey($actorId)->first();
                if (! $lockedTenant instanceof Tenant || ! $lockedActor instanceof User) {
                    throw new AuthorizationException('PERMISSION_DENIED');
                }
                [$persistedActor, $persistedTenant] = $this->authorizer->authorize(
                    $lockedActor,
                    new TenantContext($lockedTenant, $lockedActor),
                    'budget.view',
                );
                [$persistedActor, $persistedTenant] = $this->authorizer->authorize(
                    $persistedActor,
                    new TenantContext($persistedTenant, $persistedActor),
                    'expense.update',
                );
                $authorizedContext = new TenantContext($persistedTenant, $persistedActor);
                $year = $years->get((int) $targetId);
                if (! $year instanceof PlanningYear) {
                    throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$targetId]);
                }
                $effectiveDate = $this->effectiveDateValidator->validate($data->effectiveDate, (string) $persistedTenant->timezone);
                if ($year->budget_state !== BudgetState::Preparation) {
                    throw new DomainException('BUDGET_STATE_CONFLICT');
                }
                if (BudgetApproval::query()
                    ->where('tenant_id', $persistedTenant->getKey())
                    ->where('planning_year_id', $year->getKey())
                    ->where('status', BudgetApprovalStatus::Active->value)
                    ->exists()) {
                    throw new DomainException('BUDGET_STATE_CONFLICT');
                }
                if ((int) $year->lock_version !== $data->budgetLockVersion) {
                    throw new DomainException('STALE_VERSION');
                }
                if ($data->compositionSchemaVersion !== BudgetCompositionEvidence::SCHEMA_VERSION
                    || $data->projectionVersion !== BudgetCompositionEvidence::PROJECTION_VERSION) {
                    throw new DomainException('BUDGET_COMPOSITION_STALE');
                }

                /** @var AnnualEconomicProjection $projection */
                $projection = $this->engine->project(
                    $this->datasetQuery->execute($persistedActor, $authorizedContext, (int) $year->getKey()),
                );
                $sourceAccess = $this->sourceAccessResolver->resolve($persistedActor, $authorizedContext);
                $discovery = $this->composer->compose(
                    $projection,
                    (int) $year->lock_version,
                    $sourceAccess,
                    budgetState: BudgetState::Preparation->value,
                    economicBaseLockedAt: $this->economicBaseLockedAt($persistedTenant),
                );
                $this->lockContributingSources($persistedTenant, $discovery->contributors);

                $projection = $this->engine->project(
                    $this->datasetQuery->execute($persistedActor, $authorizedContext, (int) $year->getKey()),
                );
                $proposal = $this->composer->compose(
                    $projection,
                    (int) $year->lock_version,
                    $sourceAccess,
                    budgetState: BudgetState::Preparation->value,
                    economicBaseLockedAt: $this->economicBaseLockedAt($persistedTenant),
                );
                if (! hash_equals($proposal->composition->fingerprint, $data->compositionFingerprint)) {
                    throw new DomainException('BUDGET_COMPOSITION_STALE');
                }
                if ($proposal->isEmpty()) {
                    throw new DomainException('BUDGET_PROPOSAL_EMPTY');
                }

                $recordedAt = CarbonImmutable::now('UTC');
                $revision = $this->beginRevisionBatch->execute(
                    $persistedActor,
                    $authorizedContext,
                    RevisionOperation::Update,
                    'Budget proposal approval',
                    $correlationId,
                    $year,
                );
                $approval = BudgetApproval::query()->create([
                    'tenant_id' => $persistedTenant->getKey(),
                    'planning_year_id' => $year->getKey(),
                    'status' => BudgetApprovalStatus::Active,
                    'effective_date' => $effectiveDate->toDateString(),
                    'recorded_at' => $recordedAt,
                    'approved_by_user_id' => $persistedActor->getKey(),
                    'approved_by_name' => (string) $persistedActor->name,
                    'approval_note' => $data->note,
                    'currency_code' => $proposal->currency,
                    'budget_basis' => $proposal->basis,
                    'total_net_amount' => $proposal->total->net,
                    'total_vat_amount' => $proposal->total->vat,
                    'total_gross_amount' => $proposal->total->gross,
                    'total_official_amount' => $proposal->total->official,
                    'contributor_count' => count($proposal->contributors),
                    'composition_schema_version' => BudgetCompositionEvidence::SCHEMA_VERSION,
                    'projection_version' => BudgetCompositionEvidence::PROJECTION_VERSION,
                    'composition_fingerprint' => $proposal->composition->fingerprint,
                    'approval_revision_batch_id' => $revision->getKey(),
                    'correlation_id' => $correlationId,
                ]);
                foreach ($proposal->contributors as $contributor) {
                    BudgetApprovalItem::query()->create($this->itemAttributes($approval, $contributor));
                }

                $year->approveBudget();
                $version = $year->versions()->orderByDesc('id')->first();
                if (! $version instanceof Version) {
                    throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
                }
                $this->linkVersionToRevisionBatch->execute($revision, $version, 1);

                $tenant = Tenant::query()->whereKey($persistedTenant->getKey())->lockForUpdate()->firstOrFail();
                if ($tenant->economic_basis_locked_at === null) {
                    $tenant->forceFill(['economic_basis_locked_at' => $recordedAt])->save();
                }

                $this->auditRecorder->record(
                    eventType: 'budget.approved',
                    correlationId: $correlationId,
                    properties: new AuditProperties([
                        'approval_id' => (int) $approval->getKey(),
                        'planning_year_id' => (int) $year->getKey(),
                        'contributor_count' => count($proposal->contributors),
                        'basis' => $proposal->basis,
                        'composition_fingerprint' => $proposal->composition->fingerprint,
                    ]),
                    actor: $persistedActor,
                    tenantId: (int) $persistedTenant->getKey(),
                    subject: $approval,
                    occurredAt: $recordedAt,
                );

                return $approval->fresh(['items']);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000'
                && str_contains($exception->getMessage(), 'budget_approvals_active_year_unique')) {
                throw new DomainException('BUDGET_STATE_CONFLICT', previous: $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function itemAttributes(BudgetApproval $approval, ApprovalContributor $contributor): array
    {
        return [
            'tenant_id' => $approval->tenant_id,
            'planning_year_id' => $approval->planning_year_id,
            'budget_approval_id' => $approval->getKey(),
            'budget_basis' => $approval->budget_basis,
            'source_identity' => $contributor->sourceIdentity,
            'source_lock_version' => $contributor->sourceLockVersion,
            'component_kind' => $contributor->kind,
            'expense_id' => $contributor->expenseId,
            'expense_row_id' => $contributor->rowId,
            'expense_kind' => $contributor->expenseKind,
            'expense_title' => $contributor->expenseTitle,
            'row_type' => $contributor->rowType,
            'row_description' => $contributor->rowDescription,
            'cost_center_id' => $contributor->costCenterId,
            'cost_center_name' => $contributor->costCenterName,
            'vendor_id' => $contributor->vendorId,
            'vendor_name' => $contributor->vendorName,
            'project_id' => $contributor->projectId,
            'project_title' => $contributor->projectTitle,
            'contract_id' => $contributor->contractId,
            'contract_title' => $contributor->contractTitle,
            'net_amount' => $contributor->amount->net,
            'vat_amount' => $contributor->amount->vat,
            'gross_amount' => $contributor->amount->gross,
            'official_amount' => $contributor->amount->official,
        ];
    }

    private function economicBaseLockedAt(Tenant $tenant): ?string
    {
        $lockedAt = $tenant->getAttribute('economic_basis_locked_at');
        if ($lockedAt === null) {
            return null;
        }
        if (! $lockedAt instanceof CarbonInterface) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }

        return $lockedAt->toISOString();
    }

    /** @param list<ApprovalContributor> $contributors */
    private function lockContributingSources(Tenant $tenant, array $contributors): void
    {
        $ids = [
            'contracts' => [],
            'cost_centers' => [],
            'expenses' => [],
            'expense_rows' => [],
            'projects' => [],
            'vendors' => [],
        ];
        foreach ($contributors as $contributor) {
            $ids['expenses'][] = $contributor->expenseId;
            $ids['cost_centers'][] = $contributor->costCenterId;
            if ($contributor->rowId !== null) {
                $ids['expense_rows'][] = $contributor->rowId;
            }
            if ($contributor->vendorId !== null) {
                $ids['vendors'][] = $contributor->vendorId;
            }
            if ($contributor->projectId !== null) {
                $ids['projects'][] = $contributor->projectId;
            }
            if ($contributor->contractId !== null) {
                $ids['contracts'][] = $contributor->contractId;
            }
        }

        foreach ($ids as $table => $tableIds) {
            $tableIds = array_values(array_unique($tableIds));
            sort($tableIds, SORT_NUMERIC);
            if ($tableIds === []) {
                continue;
            }
            $locked = DB::table($table)
                ->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $tableIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            if ($locked->count() !== count($tableIds)) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
        }
    }
}
