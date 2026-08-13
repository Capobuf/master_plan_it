<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Budget\Data\ApprovalExclusion;
use App\Domain\Budget\Services\BudgetProposalComposer;
use App\Domain\Budget\Services\BudgetSourceAccessResolver;
use App\Domain\Budget\Services\CoherentBudgetRead;
use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\EconomicScope;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Reporting\Data\AnnualReportEvidence;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** @phpstan-type HistoricalSnapshot array{type: string, id: int, mutation: string, contents: array<string, mixed>} */
final readonly class HistoricalAnnualBudgetQuery
{
    public function __construct(
        private EconomicEngine $engine,
        private BudgetProposalComposer $composer,
        private TenantAbilityAuthorizer $authorizer,
        private CoherentBudgetRead $coherentRead,
        private BudgetSourceAccessResolver $sourceAccessResolver,
    ) {}

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, int $planningYearId, string $asOf, ?int $costCenterId = null): array
    {
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'budget.view');
        $authorizedContext = new TenantContext($persistedTenant, $persistedActor);
        $cutoff = $this->cutoff($asOf, $persistedTenant->timezone);
        $sourceAccess = $this->sourceAccessResolver->resolve($persistedActor, $authorizedContext);

        return $this->coherentRead->execute(function () use ($authorizedContext, $planningYearId, $cutoff, $sourceAccess): array {
            $tenant = Tenant::query()->find($authorizedContext->tenantId);
            $year = TenantOwnedRecordQuery::forTenant($authorizedContext, PlanningYear::class)->find($planningYearId);
            if (! $tenant instanceof Tenant || ! $year instanceof PlanningYear) {
                throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
            }
            $latest = collect($this->latestItems($authorizedContext->tenantId, $cutoff, $planningYearId));
            $yearSnapshot = $latest->first(fn (array $item): bool => $item['type'] === $this->morphClass(PlanningYear::class)
                && $item['id'] === $planningYearId && $item['mutation'] !== 'delete');
            if (! is_array($yearSnapshot)) {
                throw new DomainException('HISTORY_BEFORE_ACTIVATION');
            }
            $this->assertHistoryActivated($yearSnapshot, $cutoff);

            $expenseSnapshots = $latest->where('type', $this->morphClass(Expense::class))->keyBy('id');
            $rowSnapshots = $latest->where('type', $this->morphClass(ExpenseRow::class));
            $references = $this->referenceSnapshots(
                $authorizedContext->tenantId,
                $cutoff,
                $expenseSnapshots,
                $rowSnapshots,
            );
            [$dataset, $metadata] = $this->datasetAndMetadata(
                $authorizedContext->tenantId,
                $planningYearId,
                $yearSnapshot,
                $expenseSnapshots,
                $rowSnapshots,
                $references,
                $tenant,
            );
            $projection = $this->engine->project($dataset);
            $state = (string) ($yearSnapshot['contents']['budget_state'] ?? 'preparation');
            $lockedAt = $tenant->economic_basis_locked_at;
            $lockedAtString = $lockedAt instanceof CarbonInterface ? $lockedAt->toISOString() : null;
            $proposal = $this->composer->compose(
                $projection,
                (int) ($yearSnapshot['contents']['lock_version'] ?? 1),
                $sourceAccess,
                $metadata,
                $state,
                $lockedAtString,
            );
            $proposalArray = $proposal->toArray();
            unset($proposalArray['contributors'], $proposalArray['exclusions']);

            return [
                'planning_year' => [
                    'id' => $planningYearId,
                    'year_label' => (int) ($yearSnapshot['contents']['year_label'] ?? $year->year_label),
                    'state' => $state,
                    'lock_version' => (int) ($yearSnapshot['contents']['lock_version'] ?? 1),
                ],
                'currency' => $projection->currency,
                'basis' => $projection->basis,
                'surface_fingerprint' => $proposal->surfaceFingerprint,
                'economic_base' => [
                    'basis' => $projection->basis,
                    'locked_at' => $lockedAtString,
                ],
                'proposal' => $proposalArray,
                'approved_snapshot' => $this->approvedSnapshot($authorizedContext->tenantId, $planningYearId, $cutoff),
                'informative_evaluations' => $this->measure($this->informativeEvaluations($proposal->exclusions, $projection->basis)),
                'actuals' => $this->measure($projection->actual),
                'actions' => [
                    'can_view_approval_preview' => false,
                    'can_approve' => false,
                    'can_annul_active_approval' => false,
                ],
            ];
        });
    }

    public function reportEvidenceForAuthorizedContext(
        TenantContext $context,
        int $planningYearId,
        string $asOf,
    ): AnnualReportEvidence {
        if (DB::connection()->transactionLevel() < 1) {
            throw new \LogicException('Historical report evidence requires a coherent read boundary.');
        }

        $cutoff = $this->cutoff($asOf, $context->timezone);
        $tenant = Tenant::query()->find($context->tenantId);
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYearId);
        if (! $tenant instanceof Tenant || ! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }
        $latest = collect($this->latestItems($context->tenantId, $cutoff, $planningYearId));
        $yearSnapshot = $latest->first(fn (array $item): bool => $item['type'] === $this->morphClass(PlanningYear::class)
            && $item['id'] === $planningYearId && $item['mutation'] !== 'delete');
        if (! is_array($yearSnapshot)) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }
        $this->assertHistoryActivated($yearSnapshot, $cutoff);
        $expenseSnapshots = $latest->where('type', $this->morphClass(Expense::class))->keyBy('id');
        $rowSnapshots = $latest->where('type', $this->morphClass(ExpenseRow::class));
        $references = $this->referenceSnapshots($context->tenantId, $cutoff, $expenseSnapshots, $rowSnapshots);
        [$dataset, $metadata] = $this->datasetAndMetadata(
            $context->tenantId,
            $planningYearId,
            $yearSnapshot,
            $expenseSnapshots,
            $rowSnapshots,
            $references,
            $tenant,
        );
        $labels = ['project' => [], 'contract' => []];
        foreach ($metadata['expenses'] as $expense) {
            if ($expense['project_id'] !== null && $expense['project_title'] !== null) {
                $labels['project'][(int) $expense['project_id']] = (string) $expense['project_title'];
            }
            if ($expense['contract_id'] !== null && $expense['contract_title'] !== null) {
                $labels['contract'][(int) $expense['contract_id']] = (string) $expense['contract_title'];
            }
        }

        return new AnnualReportEvidence(
            projection: $this->engine->project($dataset),
            planningYearId: $planningYearId,
            yearLabel: (int) ($yearSnapshot['contents']['year_label'] ?? 0),
            state: (string) ($yearSnapshot['contents']['budget_state'] ?? 'preparation'),
            lockVersion: (int) ($yearSnapshot['contents']['lock_version'] ?? 1),
            warning: ($yearSnapshot['contents']['budget_state'] ?? 'preparation') === 'closed'
                ? 'BUDGET_CLOSED'
                : null,
            historyActivatedAt: isset($yearSnapshot['contents']['history_activated_at'])
                ? CarbonImmutable::parse((string) $yearSnapshot['contents']['history_activated_at'], 'UTC')
                : null,
            labels: $labels,
            cutoff: $cutoff,
        );
    }

    private function cutoff(string $value, string $timezone): CarbonImmutable
    {
        try {
            $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone)->endOfDay()
                : CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            throw new DomainException('INVALID_HISTORY_CUTOFF');
        }

        return $parsed->utc();
    }

    /** @param HistoricalSnapshot $yearSnapshot */
    private function assertHistoryActivated(array $yearSnapshot, CarbonImmutable $cutoff): void
    {
        $value = $yearSnapshot['contents']['history_activated_at'] ?? null;
        if ($value === null) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }
        try {
            $activatedAt = CarbonImmutable::parse((string) $value, 'UTC');
        } catch (\Throwable) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }
        if ($cutoff->lessThan($activatedAt)) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }
    }

    /** @return list<array{type:string,id:int,mutation:string,contents:array<string,mixed>}> */
    private function latestItems(int $tenantId, CarbonImmutable $cutoff, int $planningYearId): array
    {
        $ranked = DB::table('revision_batch_items as items')
            ->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->where('items.tenant_id', $tenantId)
            ->where('items.planning_year_id', $planningYearId)
            ->where('batches.occurred_at', '<=', $cutoff)
            ->select(['items.versionable_type', 'items.versionable_id', 'items.mutation', 'items.snapshot_contents as contents'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY items.versionable_type, items.versionable_id ORDER BY batches.occurred_at DESC, batches.id DESC, items.sequence DESC) AS revision_rank');

        return DB::query()->fromSub($ranked, 'ranked')->where('revision_rank', 1)->get()
            ->map(static function (object $row): array {
                $contents = is_string($row->contents)
                    ? json_decode($row->contents, true, 512, JSON_THROW_ON_ERROR)
                    : (array) $row->contents;

                return [
                    'type' => (string) $row->versionable_type,
                    'id' => (int) $row->versionable_id,
                    'mutation' => (string) $row->mutation,
                    'contents' => $contents,
                ];
            })->all();
    }

    /**
     * @param  Collection<array-key, HistoricalSnapshot>  $latest
     * @return array<class-string, array<int, HistoricalSnapshot>>
     */
    private function references(Collection $latest): array
    {
        $result = [];
        foreach ([CostCenter::class, Vendor::class, Project::class, Contract::class] as $model) {
            $result[$model] = $latest->where('type', $this->morphClass($model))->mapWithKeys(
                static fn (array $item): array => [$item['id'] => $item],
            )->all();
        }

        return $result;
    }

    /**
     * Reference revision items are not annual subjects, so their planning_year_id is null.
     * Resolve the exact IDs referenced by annual expense/row snapshots in one tenant-scoped query.
     *
     * @param  Collection<array-key, HistoricalSnapshot>  $expenseSnapshots
     * @param  Collection<array-key, HistoricalSnapshot>  $rowSnapshots
     * @return array<class-string, array<int, HistoricalSnapshot>>
     */
    private function referenceSnapshots(
        int $tenantId,
        CarbonImmutable $cutoff,
        Collection $expenseSnapshots,
        Collection $rowSnapshots,
    ): array {
        $ids = [CostCenter::class => [], Project::class => [], Contract::class => [], Vendor::class => []];
        foreach ($expenseSnapshots as $snapshot) {
            $contents = $snapshot['contents'];
            foreach (['cost_center_id' => CostCenter::class, 'project_id' => Project::class, 'contract_id' => Contract::class] as $key => $model) {
                if (isset($contents[$key])) {
                    $ids[$model][(int) $contents[$key]] = true;
                }
            }
        }
        foreach ($rowSnapshots as $snapshot) {
            if (isset($snapshot['contents']['vendor_id'])) {
                $ids[Vendor::class][(int) $snapshot['contents']['vendor_id']] = true;
            }
        }
        if (array_sum(array_map('count', $ids)) === 0) {
            return $this->references(collect());
        }

        $ranked = DB::table('revision_batch_items as items')
            ->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->where('items.tenant_id', $tenantId)
            ->where('batches.occurred_at', '<=', $cutoff)
            ->where(function ($query) use ($ids): void {
                foreach ($ids as $model => $modelIds) {
                    if ($modelIds === []) {
                        continue;
                    }
                    $query->orWhere(function ($reference) use ($model, $modelIds): void {
                        $reference->where('items.versionable_type', $this->morphClass($model))
                            ->whereIn('items.versionable_id', array_keys($modelIds));
                    });
                }
            })
            ->select(['items.versionable_type', 'items.versionable_id', 'items.mutation', 'items.snapshot_contents as contents'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY items.versionable_type, items.versionable_id ORDER BY batches.occurred_at DESC, batches.id DESC, items.sequence DESC) AS revision_rank');
        /** @var Collection<int, HistoricalSnapshot> $snapshots */
        $snapshots = DB::query()->fromSub($ranked, 'ranked')->where('revision_rank', 1)->get()
            ->map(static function (object $row): array {
                $contents = is_string($row->contents)
                    ? json_decode($row->contents, true, 512, JSON_THROW_ON_ERROR)
                    : (array) $row->contents;

                return [
                    'type' => (string) $row->versionable_type,
                    'id' => (int) $row->versionable_id,
                    'mutation' => (string) $row->mutation,
                    'contents' => $contents,
                ];
            });

        return $this->references($snapshots);
    }

    /**
     * @param  array<string, mixed>  $yearSnapshot
     * @param  Collection<array-key, HistoricalSnapshot>  $expenseSnapshots
     * @param  Collection<array-key, HistoricalSnapshot>  $rowSnapshots
     * @param  array<class-string, array<int, HistoricalSnapshot>>  $references
     * @return array{EconomicDataset, array{expenses: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>}}
     */
    private function datasetAndMetadata(
        int $tenantId,
        int $planningYearId,
        array $yearSnapshot,
        Collection $expenseSnapshots,
        Collection $rowSnapshots,
        array $references,
        Tenant $tenant,
    ): array {
        $expenses = [];
        foreach ($expenseSnapshots as $id => $snapshot) {
            $contents = $snapshot['contents'];
            $costCenterId = (int) ($contents['cost_center_id'] ?? 0);
            $projectId = isset($contents['project_id']) ? (int) $contents['project_id'] : null;
            $contractId = isset($contents['contract_id']) ? (int) $contents['contract_id'] : null;
            $costCenter = $references[CostCenter::class][$costCenterId] ?? null;
            $project = $projectId === null ? null : ($references[Project::class][$projectId] ?? null);
            $contract = $contractId === null ? null : ($references[Contract::class][$contractId] ?? null);
            $expenses[(int) $id] = [
                'id' => (int) $id,
                'title' => (string) ($contents['title'] ?? ''),
                'kind' => (string) ($contents['kind'] ?? 'ordinary'),
                'lock_version' => (int) ($contents['lock_version'] ?? 1),
                'cost_center_id' => $costCenterId,
                'cost_center_exists' => $costCenter !== null,
                'cost_center_name' => (string) ($costCenter['contents']['name'] ?? '—'),
                'cost_center_deleted_at' => $this->deletedAt($costCenter),
                'project_id' => $projectId,
                'project_exists' => $projectId === null || $project !== null,
                'project_title' => $projectId === null ? null : (string) ($project['contents']['title'] ?? '—'),
                'project_deleted_at' => $this->deletedAt($project),
                'contract_id' => $contractId,
                'contract_exists' => $contractId === null || $contract !== null,
                'contract_title' => $contractId === null ? null : (string) ($contract['contents']['title'] ?? '—'),
                'contract_deleted_at' => $this->deletedAt($contract),
                'deleted_at' => $this->deletedAt($snapshot),
                'current_planning_row_id' => isset($contents['current_planning_row_id']) ? (int) $contents['current_planning_row_id'] : null,
            ];
        }

        $rows = [];
        $lines = [];
        foreach ($rowSnapshots as $snapshot) {
            $contents = $snapshot['contents'];
            $expenseId = (int) ($contents['expense_id'] ?? 0);
            $expense = $expenses[$expenseId] ?? null;
            if (! is_array($expense)) {
                continue;
            }
            $vendorId = isset($contents['vendor_id']) ? (int) $contents['vendor_id'] : null;
            $vendor = $vendorId === null ? null : ($references[Vendor::class][$vendorId] ?? null);
            $row = [
                'id' => (int) $snapshot['id'],
                'expense_id' => $expenseId,
                'lock_version' => (int) ($contents['lock_version'] ?? 1),
                'type' => (string) ($contents['type'] ?? 'estimate'),
                'description' => (string) ($contents['description'] ?? ''),
                'vendor_id' => $vendorId,
                'vendor_exists' => $vendorId === null || $vendor !== null,
                'vendor_name' => $vendorId === null ? null : (string) ($vendor['contents']['name'] ?? '—'),
                'vendor_deleted_at' => $this->deletedAt($vendor),
                'net_amount' => $this->decimal($contents['net_amount'] ?? '0'),
                'vat_amount' => $this->decimal($contents['vat_amount'] ?? '0'),
                'gross_amount' => $this->decimal($contents['gross_amount'] ?? '0'),
                'deleted_at' => $this->deletedAt($snapshot),
                'expense_deleted_at' => $expense['deleted_at'],
            ];
            $rows[$snapshot['id']] = $row;
            if ($row['deleted_at'] !== null || $expense['deleted_at'] !== null) {
                continue;
            }
            $fundedPlafondId = isset($contents['funded_plafond_expense_id']) ? (int) $contents['funded_plafond_expense_id'] : null;
            $fundedExpense = $fundedPlafondId === null ? null : ($expenses[$fundedPlafondId] ?? null);
            $lines[] = new EconomicLine(
                $expenseId,
                (int) $snapshot['id'],
                (string) $expense['kind'],
                (string) $row['type'],
                isset($contents['confirmation_state']) ? (string) $contents['confirmation_state'] : null,
                (int) $expense['cost_center_id'],
                (string) $expense['cost_center_name'],
                (string) $row['net_amount'],
                (string) $row['vat_amount'],
                (string) $row['gross_amount'],
                $fundedPlafondId,
                isset($contents['spend_date']) ? (string) $contents['spend_date'] : null,
                isset($contents['period_start']) ? (string) $contents['period_start'] : null,
                isset($contents['period_end']) ? (string) $contents['period_end'] : null,
                isset($contents['distribution']) ? (string) $contents['distribution'] : null,
                (bool) ($contents['is_extra'] ?? false),
                $expense['project_id'],
                null,
                $expense['project_title'],
                $expense['current_planning_row_id'] === (int) $snapshot['id'],
                (string) $row['description'],
                isset($contents['notes']) ? (string) $contents['notes'] : null,
                $vendorId,
                $row['vendor_name'],
                $expense['contract_id'],
                (string) $expense['title'],
                isset($contents['created_by_user_id']) ? (int) $contents['created_by_user_id'] : null,
                null,
                $fundedExpense['title'] ?? null,
                $fundedExpense['cost_center_id'] ?? null,
                $fundedExpense['cost_center_name'] ?? null,
            );
        }

        $basis = $tenant->budget_basis instanceof BudgetBasis
            ? $tenant->budget_basis
            : BudgetBasis::from((string) $tenant->budget_basis);
        $dataset = new EconomicDataset(
            new EconomicScope(
                $tenantId,
                $planningYearId,
                (int) ($yearSnapshot['contents']['year_label'] ?? 0),
                (string) $tenant->currency_code,
                $basis,
            ),
            $lines,
        );

        return [$dataset, ['expenses' => $expenses, 'rows' => $rows]];
    }

    /** @param array<string, mixed>|null $snapshot */
    private function deletedAt(?array $snapshot): ?string
    {
        if ($snapshot === null) {
            return null;
        }

        return $snapshot['mutation'] === 'delete'
            ? (string) ($snapshot['contents']['deleted_at'] ?? 'deleted')
            : ($snapshot['contents']['deleted_at'] ?? null);
    }

    /** @return array<string, mixed>|null */
    private function approvedSnapshot(int $tenantId, int $planningYearId, CarbonImmutable $cutoff): ?array
    {
        $approval = DB::table('budget_approvals')
            ->where('tenant_id', $tenantId)
            ->where('planning_year_id', $planningYearId)
            ->where('recorded_at', '<=', $cutoff)
            ->where(fn ($query) => $query->whereNull('annulled_at')->orWhere('annulled_at', '>', $cutoff))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
        if ($approval === null) {
            return null;
        }

        return [
            'id' => (int) $approval->id,
            'status' => 'active',
            'effective_date' => (string) $approval->effective_date,
            'recorded_at' => CarbonImmutable::parse((string) $approval->recorded_at, 'UTC')->toISOString(),
            'total' => [
                'net' => (string) $approval->total_net_amount,
                'vat' => (string) $approval->total_vat_amount,
                'gross' => (string) $approval->total_gross_amount,
                'official' => (string) $approval->total_official_amount,
            ],
        ];
    }

    /** @param list<ApprovalExclusion> $exclusions */
    private function informativeEvaluations(array $exclusions, string $basis): EconomicMeasure
    {
        $measure = EconomicMeasure::zero($basis);
        foreach ($exclusions as $exclusion) {
            if (in_array($exclusion->reason, ['alternative_planning', 'covered_by_plafond', 'non_current_planning'], true)) {
                $measure = $measure->plus($exclusion->amount, $basis);
            }
        }

        return $measure;
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }

    /** @param class-string $class */
    private function morphClass(string $class): string
    {
        return (new $class)->getMorphClass();
    }
}
