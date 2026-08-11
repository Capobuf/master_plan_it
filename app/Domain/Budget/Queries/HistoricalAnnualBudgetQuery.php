<?php

namespace App\Domain\Budget\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Queries\TenantOwnedRecordQuery;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class HistoricalAnnualBudgetQuery
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $referenceSnapshotCache = [];

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, int $planningYearId, string $asOf, ?int $costCenterId = null): array
    {
        $this->referenceSnapshotCache = [];
        $actorQuery = $actor->tenant_id === null
            ? $actor->newQuery()
            : TenantOwnedRecordQuery::forTenant($context, User::class);
        $persistedActor = $actorQuery->whereKey($actor->getRawOriginal($actor->getKeyName()))->where('is_active', true)->first();
        if (! $persistedActor instanceof User || ($persistedActor->tenant_id !== null && (int) $persistedActor->tenant_id !== $context->tenantId)) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }
        $year = TenantOwnedRecordQuery::forTenant($context, PlanningYear::class)->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }
        $cutoff = $this->cutoff($asOf, $context->timezone);
        $activatedAt = $year->history_activated_at;
        if (! $activatedAt instanceof CarbonInterface || $cutoff->lessThan($activatedAt)) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }

        $latest = $this->latestItems($context->tenantId, $cutoff, $planningYearId);
        $expenseType = $this->morphClass(Expense::class);
        $rowType = $this->morphClass(ExpenseRow::class);
        $yearType = $this->morphClass(PlanningYear::class);
        $yearSnapshot = collect($latest)->first(fn (array $item): bool => $item['type'] === $yearType && $item['id'] === $planningYearId && $item['mutation'] !== 'delete');
        if (! is_array($yearSnapshot)) {
            throw new DomainException('HISTORY_BEFORE_ACTIVATION');
        }

        $expenseSnapshots = collect($latest)->filter(fn (array $item): bool => $item['type'] === $expenseType && $item['mutation'] !== 'delete')
            ->filter(fn (array $item): bool => $costCenterId === null || (int) ($item['contents']['cost_center_id'] ?? 0) === $costCenterId)->values();
        $expenseIds = $expenseSnapshots->pluck('id')->all();
        $rowSnapshots = collect($latest)->filter(fn (array $item): bool => $item['type'] === $rowType && $item['mutation'] !== 'delete')
            ->filter(fn (array $item): bool => in_array((int) ($item['contents']['expense_id'] ?? 0), $expenseIds, true))->values();
        $labels = $this->labels($context->tenantId, $cutoff, $expenseSnapshots->all(), $rowSnapshots->all());
        $basis = $context->budgetBasis->value;
        $rows = [];
        foreach ($expenseSnapshots as $snapshot) {
            $contents = $snapshot['contents'];
            $expenseRows = $rowSnapshots->filter(fn (array $row): bool => (int) ($row['contents']['expense_id'] ?? 0) === $snapshot['id']);
            $selectedId = isset($contents['current_planning_row_id']) ? (int) $contents['current_planning_row_id'] : null;
            $selected = $expenseRows->first(fn (array $row): bool => $row['id'] === $selectedId);
            $planned = is_array($selected) ? $this->decimal($selected['contents'][$basis.'_amount'] ?? '0') : null;
            $actual = $expenseRows->filter(fn (array $row): bool => ($row['contents']['type'] ?? null) === 'actual')
                ->reduce(fn (string $sum, array $row): string => bcadd($sum, $this->decimal($row['contents'][$basis.'_amount'] ?? '0'), 2), '0.00');
            $approved = array_key_exists('approved_amount', $contents) && $contents['approved_amount'] !== null ? $this->decimal($contents['approved_amount']) : null;
            $state = (string) ($contents['state'] ?? 'open');
            $rows[] = [
                'id' => $snapshot['id'], 'lock_version' => (int) ($contents['lock_version'] ?? 1), 'title' => (string) ($contents['title'] ?? ''),
                'kind' => (string) ($contents['kind'] ?? 'ordinary'), 'cost_center_id' => (int) ($contents['cost_center_id'] ?? 0),
                'cost_center_name' => $labels['cost_center'][(int) ($contents['cost_center_id'] ?? 0)] ?? '—',
                'project_id' => isset($contents['project_id']) ? (int) $contents['project_id'] : null,
                'project_title' => $labels['project'][(int) ($contents['project_id'] ?? 0)] ?? null,
                'contract_id' => isset($contents['contract_id']) ? (int) $contents['contract_id'] : null,
                'contract_title' => $labels['contract'][(int) ($contents['contract_id'] ?? 0)] ?? null,
                'vendor_id' => is_array($selected) && isset($selected['contents']['vendor_id']) ? (int) $selected['contents']['vendor_id'] : null,
                'vendor_name' => is_array($selected) ? ($labels['vendor'][(int) ($selected['contents']['vendor_id'] ?? 0)] ?? null) : null,
                'state' => $state, 'closure_outcome' => $contents['closure_outcome'] ?? null,
                'current_planning_row_id' => $selectedId,
                'funded_plafond_expense_id' => is_array($selected) && isset($selected['contents']['funded_plafond_expense_id']) ? (int) $selected['contents']['funded_plafond_expense_id'] : null,
                'planned' => $planned, 'approved' => $approved, 'approved_basis' => $contents['approved_basis'] ?? null, 'actual' => $actual,
                'residual' => $approved === null ? null : bcsub($approved, $actual, 2), 'variance' => $approved === null ? null : bcsub($actual, $approved, 2),
                'variance_final' => $state === 'closed', 'has_actual' => $expenseRows->contains(fn (array $row): bool => ($row['contents']['type'] ?? null) === 'actual'),
                'rows' => $expenseRows->map(fn (array $row): array => ['id' => $row['id'], ...$row['contents']])->values()->all(),
            ];
        }

        $summary = $this->summary($rows, $context->tenantId, $planningYearId, $cutoff);
        $historicalContext = $this->historicalContext(
            $context->tenantId,
            $planningYearId,
            $cutoff,
            $expenseSnapshots->all(),
            $rowSnapshots->all(),
        );
        $yearContents = $yearSnapshot['contents'];
        $state = (string) ($yearContents['budget_state'] ?? 'preparation');

        return [
            'mode' => 'historical', 'requested_as_of' => $asOf, 'cutoff_utc' => $cutoff->toISOString(), 'read_only' => true,
            'budget' => ['planning_year_id' => $planningYearId, 'year' => (int) ($yearContents['year_label'] ?? $year->year_label), 'state' => $state,
                'lock_version' => (int) ($yearContents['lock_version'] ?? 1), 'warning' => $state === 'closed' ? 'BUDGET_CLOSED' : null,
                'history_activated_at' => $activatedAt->toISOString()],
            'summary' => ['currency' => $context->currencyCode, 'official_basis' => $basis, ...$summary], 'expenses' => $rows,
            'historical_context' => $historicalContext,
        ];
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

    /** @return list<array{type:string,id:int,mutation:string,contents:array<string,mixed>}> */
    private function latestItems(int $tenantId, CarbonImmutable $cutoff, int $planningYearId): array
    {
        $ranked = DB::table('revision_batch_items as items')->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->where('items.tenant_id', $tenantId)
            ->where('items.planning_year_id', $planningYearId)->where('batches.occurred_at', '<=', $cutoff)
            ->select(['items.versionable_type', 'items.versionable_id', 'items.mutation', 'items.snapshot_contents as contents'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY items.versionable_type, items.versionable_id ORDER BY batches.occurred_at DESC, batches.id DESC, items.sequence DESC) AS revision_rank');

        return DB::query()->fromSub($ranked, 'ranked')->where('revision_rank', 1)->get()->map(function (object $row): array {
            $contents = is_string($row->contents) ? json_decode($row->contents, true, 512, JSON_THROW_ON_ERROR) : (array) $row->contents;

            return ['type' => (string) $row->versionable_type, 'id' => (int) $row->versionable_id, 'mutation' => (string) $row->mutation, 'contents' => $contents];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @param  list<array<string, mixed>>  $rows
     * @return array{cost_center: array<int, string>, project: array<int, string>, contract: array<int, string>, vendor: array<int, string>}
     */
    private function labels(int $tenantId, CarbonImmutable $cutoff, array $expenses, array $rows): array
    {
        $targets = [
            'cost_center' => [$this->morphClass(CostCenter::class), collect($expenses)->pluck('contents.cost_center_id')->filter()->unique()->all(), 'name'],
            'project' => [$this->morphClass(Project::class), collect($expenses)->pluck('contents.project_id')->filter()->unique()->all(), 'title'],
            'contract' => [$this->morphClass(Contract::class), collect($expenses)->pluck('contents.contract_id')->filter()->unique()->all(), 'title'],
            'vendor' => [$this->morphClass(Vendor::class), collect($rows)->pluck('contents.vendor_id')->filter()->unique()->all(), 'name'],
        ];
        $result = ['cost_center' => [], 'project' => [], 'contract' => [], 'vendor' => []];
        foreach ($targets as $key => [$type, $ids, $field]) {
            /** @var class-string<Model> $model */
            $model = match ($type) {
                $this->morphClass(CostCenter::class) => CostCenter::class,
                $this->morphClass(Project::class) => Project::class,
                $this->morphClass(Contract::class) => Contract::class,
                default => Vendor::class,
            };
            foreach ($this->referenceSnapshots($tenantId, $cutoff, $model, $ids) as $snapshot) {
                $result[$key][(int) $snapshot['id']] = (string) ($snapshot[$field] ?? '—');
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function historicalContext(int $tenantId, int $planningYearId, CarbonImmutable $cutoff, array $expenses, array $rows): array
    {
        $costCenterIds = collect($expenses)->pluck('contents.cost_center_id')->filter()->unique()->values()->all();
        $projectIds = collect($expenses)->pluck('contents.project_id')->filter()->unique()->values()->all();
        $contractIds = collect($expenses)->pluck('contents.contract_id')->filter()->unique()->values()->all();
        $vendorIds = collect($rows)->pluck('contents.vendor_id')->filter()->unique()->values()->all();

        $projects = $this->referenceSnapshots($tenantId, $cutoff, Project::class, $projectIds);
        $contracts = $this->referenceSnapshots($tenantId, $cutoff, Contract::class, $contractIds);
        $costCenterIds = collect($costCenterIds)
            ->concat(collect($projects)->pluck('cost_center_id'))
            ->concat(collect($contracts)->pluck('cost_center_id'))
            ->filter()->unique()->values()->all();
        $vendorIds = collect($vendorIds)->concat(collect($contracts)->pluck('vendor_id'))->filter()->unique()->values()->all();

        $termIds = DB::table('revision_batch_items as items')
            ->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->where('items.tenant_id', $tenantId)
            ->where('items.versionable_type', $this->morphClass(ContractTerm::class))
            ->where('batches.occurred_at', '<=', $cutoff)
            ->whereIn('items.operational_root_id', $contractIds)
            ->distinct()
            ->pluck('items.versionable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $terms = collect($this->referenceSnapshots($tenantId, $cutoff, ContractTerm::class, $termIds))
            ->values()->all();

        return [
            'approval_operations' => $this->approvalSnapshots($tenantId, $planningYearId, $cutoff),
            'cost_centers' => $this->referenceSnapshots($tenantId, $cutoff, CostCenter::class, $costCenterIds),
            'projects' => $projects,
            'contracts' => $contracts,
            'contract_terms' => $terms,
            'vendors' => $this->referenceSnapshots($tenantId, $cutoff, Vendor::class, $vendorIds),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function referenceSnapshots(int $tenantId, CarbonImmutable $cutoff, string $model, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        sort($ids);
        $cacheKey = $tenantId.'|'.$cutoff->toISOString().'|'.$model.'|'.implode(',', $ids);
        if (array_key_exists($cacheKey, $this->referenceSnapshotCache)) {
            return $this->referenceSnapshotCache[$cacheKey];
        }

        $ranked = DB::table('revision_batch_items as items')->join('revision_batches as batches', 'batches.id', '=', 'items.revision_batch_id')
            ->where('items.tenant_id', $tenantId)
            ->where('items.versionable_type', $this->morphClass($model))->whereIn('items.versionable_id', $ids)
            ->where('batches.occurred_at', '<=', $cutoff)
            ->select(['items.versionable_id', 'items.mutation', 'items.operational_root_id', 'items.snapshot_contents as contents'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY items.versionable_id ORDER BY batches.occurred_at DESC, batches.id DESC, items.sequence DESC) AS revision_rank');

        return $this->referenceSnapshotCache[$cacheKey] = DB::query()->fromSub($ranked, 'ranked')->where('revision_rank', 1)->where('mutation', 'upsert')->get()
            ->map(function (object $row) use ($model): array {
                $contents = json_decode((string) $row->contents, true, 512, JSON_THROW_ON_ERROR);
                if ($model === ContractTerm::class && ! array_key_exists('contract_id', $contents)) {
                    $contents['contract_id'] = (int) $row->operational_root_id;
                }

                return ['id' => (int) $row->versionable_id, ...$contents];
            })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function approvalSnapshots(int $tenantId, int $planningYearId, CarbonImmutable $cutoff): array
    {
        $operations = DB::table('approval_operations')->where('tenant_id', $tenantId)
            ->where('planning_year_id', $planningYearId)->where('recorded_at', '<=', $cutoff)
            ->orderBy('recorded_at')->orderBy('id')->get();
        if ($operations->isEmpty()) {
            return [];
        }
        $items = DB::table('approval_items')->whereIn('approval_operation_id', $operations->pluck('id'))
            ->orderBy('id')->get()->groupBy('approval_operation_id');

        return $operations->map(static fn (object $operation): array => [
            'id' => (int) $operation->id,
            'kind' => (string) $operation->kind,
            'effective_date' => (string) $operation->effective_date,
            'recorded_at' => (string) $operation->recorded_at,
            'actor_user_id' => (int) $operation->actor_user_id,
            'reason' => $operation->reason === null ? null : (string) $operation->reason,
            'budget_basis' => (string) $operation->budget_basis,
            'correlation_id' => (string) $operation->correlation_id,
            'items' => $items->get($operation->id, collect())->map(static fn (object $item): array => [
                'id' => (int) $item->id,
                'expense_id' => (int) $item->expense_id,
                'previous_amount' => $item->previous_amount === null ? null : (string) $item->previous_amount,
                'new_amount' => (string) $item->new_amount,
                'delta_amount' => (string) $item->delta_amount,
                'cost_center_id' => (int) $item->cost_center_id,
                'project_id' => $item->project_id === null ? null : (int) $item->project_id,
                'contract_id' => $item->contract_id === null ? null : (int) $item->contract_id,
                'expense_kind' => (string) $item->expense_kind,
                'budget_basis' => (string) $item->budget_basis,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(array $rows, int $tenantId, int $planningYearId, CarbonImmutable $cutoff): array
    {
        $proposed = $approved = $actual = '0.00';
        $open = $closed = $unapproved = 0;
        $overrun = '0.00';
        foreach ($rows as $row) {
            if (! ($row['state'] === 'closed' && in_array($row['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true)) && $row['planned'] !== null) {
                $proposed = bcadd($proposed, $row['planned'], 2);
            }
            if ($row['approved'] !== null) {
                $approved = bcadd($approved, $row['approved'], 2);
            }
            $actual = bcadd($actual, $row['actual'], 2);
            $row['state'] === 'open' ? $open++ : $closed++;
            if ($row['approved'] === null && $row['has_actual']) {
                $unapproved++;
            }
        }
        [$proposed, $approved, $overrun] = $this->reconcilePlafond($rows, $proposed, $approved);
        $operations = DB::table('approval_operations')->where('tenant_id', $tenantId)->where('planning_year_id', $planningYearId)->where('recorded_at', '<=', $cutoff);
        $firstId = (clone $operations)->orderBy('recorded_at')->orderBy('id')->value('id');
        $initial = $firstId === null ? '0.00' : $this->decimal(DB::table('approval_items')->where('approval_operation_id', $firstId)->sum('new_amount'));
        $variations = $this->decimal(DB::table('approval_items')->join('approval_operations', 'approval_operations.id', '=', 'approval_items.approval_operation_id')
            ->where('approval_operations.tenant_id', $tenantId)->where('approval_operations.planning_year_id', $planningYearId)
            ->where('approval_operations.recorded_at', '<=', $cutoff)->where('approval_operations.kind', 'variation')->sum('approval_items.delta_amount'));

        return ['proposed' => $proposed, 'initial_approved' => $initial, 'approved_variations' => $variations, 'approved_current' => $approved,
            'actual' => $actual, 'residual' => bcsub($approved, $actual, 2), 'variance' => bcsub($actual, $approved, 2),
            'utilization_percentage' => bccomp($approved, '0', 2) === 1 ? bcdiv(bcmul($actual, '100', 4), $approved, 2) : null,
            'plafond_overrun' => $overrun, 'open_expenses' => $open, 'closed_expenses' => $closed, 'unapproved_actual_expenses' => $unapproved];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{string,string,string}
     */
    private function reconcilePlafond(array $rows, string $proposed, string $approved): array
    {
        $byId = [];
        $consumers = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
            if ($row['funded_plafond_expense_id'] !== null) {
                $consumers[(int) $row['funded_plafond_expense_id']][] = $row;
            }
        }
        $overrun = '0.00';
        foreach ($consumers as $plafondId => $fundedRows) {
            $plafond = $byId[$plafondId] ?? null;
            if (! is_array($plafond) || $plafond['kind'] !== 'plafond') {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $plafondExcluded = $this->excludedFromProposal($plafond);
            $consumerPlanned = array_reduce(
                $fundedRows,
                fn (string $sum, array $row): string => $this->excludedFromProposal($row)
                    ? $sum
                    : bcadd($sum, $row['planned'] ?? '0.00', 2),
                '0.00',
            );
            $consumerApproved = array_reduce($fundedRows, static fn (string $sum, array $row): string => bcadd($sum, $row['approved'] ?? '0.00', 2), '0.00');
            $consumerActual = array_reduce($fundedRows, static fn (string $sum, array $row): string => bcadd($sum, $row['actual'], 2), '0.00');
            if (! $plafondExcluded) {
                $proposed = bcsub($proposed, $this->minimum($plafond['planned'] ?? '0.00', $consumerPlanned), 2);
            }
            $approved = bcsub($approved, $this->minimum($plafond['approved'] ?? '0.00', $consumerApproved), 2);
            $available = $plafond['approved'] ?? $plafond['planned'] ?? '0.00';
            if (bccomp($consumerActual, $available, 2) === 1) {
                $overrun = bcadd($overrun, bcsub($consumerActual, $available, 2), 2);
            }
        }

        return [$proposed, $approved, $overrun];
    }

    /** @param array<string, mixed> $row */
    private function excludedFromProposal(array $row): bool
    {
        return $row['state'] === 'closed' && in_array($row['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true);
    }

    private function minimum(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function decimal(mixed $value): string
    {
        $value = (string) ($value ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ($whole === '-0' ? '0' : $whole).'.'.substr(str_pad($fraction, 2, '0'), 0, 2);
    }

    /** @param class-string<Model> $model */
    private function morphClass(string $model): string
    {
        return app($model)->getMorphClass();
    }
}
