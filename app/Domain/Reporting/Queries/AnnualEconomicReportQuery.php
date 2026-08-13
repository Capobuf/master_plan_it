<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Budget\Services\CoherentBudgetRead;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\EconomicEngine;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use App\Domain\Reporting\Data\AnnualReportEvidence;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\PlanningYear;
use App\Models\User;
use App\Support\Authorization\TenantAbilityAuthorizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class AnnualEconomicReportQuery
{
    public function __construct(
        private EconomicDatasetQuery $datasetQuery,
        private EconomicEngine $engine,
        private HistoricalAnnualBudgetQuery $historicalBudgetQuery,
        private TenantAbilityAuthorizer $authorizer,
        private CoherentBudgetRead $coherentRead,
    ) {}

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, EconomicReportFilterData $filter): array
    {
        if (! in_array($filter->groupBy, ['cost_center', 'project', 'contract', 'vendor', 'expense'], true)) {
            throw new DomainException('INVALID_REPORT_GROUPING');
        }
        [$persistedActor, $persistedTenant] = $this->authorizer->authorize($actor, $context, 'report.view');
        $authorizedContext = new TenantContext($persistedTenant, $persistedActor);

        return $this->coherentRead->execute(function () use ($authorizedContext, $filter, $persistedActor): array {
            $evidence = $filter->asOf === null
                ? $this->currentEvidence($persistedActor, $authorizedContext, $filter->planningYearId)
                : $this->historicalBudgetQuery->reportEvidenceForAuthorizedContext(
                    $authorizedContext,
                    $filter->planningYearId,
                    $filter->asOf,
                );
            $projection = $evidence->projection;
            $approvalItems = $this->approvalItems($authorizedContext, $filter);
            $groups = $this->groups($projection, $filter, $evidence->labels, $approvalItems);
            usort($groups, static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']) ?: strcmp((string) $left['key'], (string) $right['key']));
            $totals = $this->sumProjectionTotals($groups, $projection->basis);
            $approved = array_reduce($groups, static fn (string $sum, array $group): string => bcadd($sum, (string) $group['approved'], 2), '0.00');
            $unapproved = array_reduce($groups, static fn (int $sum, array $group): int => $sum + (int) $group['unapproved_actual_expenses'], 0);
            $total = count($groups);
            $lastPage = max(1, (int) ceil($total / $filter->perPage));
            $page = min(max($filter->page, 1), $lastPage);

            return [
                'data' => array_slice($groups, ($page - 1) * $filter->perPage, $filter->perPage),
                'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $filter->perPage, 'total' => $total],
                'mode' => $filter->asOf === null ? 'current' : 'historical',
                'requested_as_of' => $filter->asOf,
                'cutoff_utc' => $evidence->cutoff?->toISOString(),
                'read_only' => $filter->asOf !== null,
                'budget' => [
                    'planning_year_id' => $evidence->planningYearId,
                    'year' => $evidence->yearLabel,
                    'state' => $evidence->state,
                    'lock_version' => $evidence->lockVersion,
                ],
                'currency' => $projection->currency,
                'basis' => $projection->basis,
                'totals' => $totals,
                'summary' => [
                    'currency' => $projection->currency,
                    'official_basis' => $projection->basis,
                    'proposed' => $totals['current_planning']['official'],
                    'approved_current' => $approved,
                    'actual' => $totals['actual']['official'],
                    'residual' => bcsub($approved, $totals['actual']['official'], 2),
                    'variance' => bcsub($totals['actual']['official'], $approved, 2),
                    'utilization_percentage' => $this->utilization($totals['actual']['official'], $approved),
                    'unapproved_actual_expenses' => $unapproved,
                ],
                'plafonds' => array_values(array_map(static fn ($plafond): array => [
                    'id' => $plafond->plafondExpenseId,
                    'planning_year_id' => $plafond->planningYearId,
                    'title' => $plafond->title,
                    'cost_center' => ['id' => $plafond->costCenterId, 'name' => $plafond->costCenterName],
                    'currency' => $plafond->currency,
                    'basis' => $plafond->basis,
                    'measures' => PlafondProjectionSerializer::measures($plafond),
                ], array_filter($projection->plafonds, static fn ($plafond): bool => $filter->costCenterId === null || $plafond->costCenterId === $filter->costCenterId))),
                'filters' => [
                    'planning_year_id' => $filter->planningYearId, 'cost_center_id' => $filter->costCenterId,
                    'project_id' => $filter->projectId, 'contract_id' => $filter->contractId,
                    'vendor_id' => $filter->vendorId, 'group_by' => $filter->groupBy, 'as_of' => $filter->asOf,
                ],
            ];
        });
    }

    private function currentEvidence(User $actor, TenantContext $context, int $planningYearId): AnnualReportEvidence
    {
        $year = PlanningYear::query()->where('tenant_id', $context->tenantId)->find($planningYearId);
        if (! $year instanceof PlanningYear) {
            throw (new ModelNotFoundException)->setModel(PlanningYear::class, [$planningYearId]);
        }
        $state = $year->budget_state instanceof \BackedEnum ? $year->budget_state->value : (string) $year->budget_state;

        return new AnnualReportEvidence(
            projection: $this->engine->project($this->datasetQuery->execute($actor, $context, $planningYearId)),
            planningYearId: (int) $year->getKey(),
            yearLabel: (int) $year->year_label,
            state: $state,
            lockVersion: (int) $year->lock_version,
            labels: $this->labels($context, $planningYearId),
            cutoff: null,
        );
    }

    /**
     * @param  array<string, array<int, string>>  $labels
     * @param  list<array<string, mixed>>  $approvalItems
     * @return list<array<string, mixed>>
     */
    private function groups(AnnualEconomicProjection $projection, EconomicReportFilterData $filter, array $labels, array $approvalItems): array
    {
        $groups = [];
        $actualExpenses = [];
        foreach ($projection->lines as $line) {
            if (! $this->included($line, $filter)) {
                continue;
            }
            [$key, $label] = $this->groupIdentity($line, $filter->groupBy, $labels);
            $groups[$key] ??= $this->group($key, $label, $filter->groupBy, $line, $projection);
            if ($line->contributesToCurrentPlanning) {
                $groups[$key]['totals']['current_planning'] = $this->addMeasure($groups[$key]['totals']['current_planning'], $line->amount, $projection->basis);
            }
            if ($line->type === 'actual') {
                $groups[$key]['totals']['actual'] = $this->addMeasure($groups[$key]['totals']['actual'], $line->amount, $projection->basis);
                $actualExpenses[$key][$line->expenseId] = true;
            }
            $groups[$key]['lines'][] = $this->line($line);
        }
        $approvedExpenses = [];
        foreach ($approvalItems as $item) {
            [$key] = $this->approvalIdentity($item, $filter->groupBy);
            if (! isset($groups[$key])) {
                continue;
            }
            $groups[$key]['approved'] = bcadd($groups[$key]['approved'], (string) $item['official_amount'], 2);
            $approvedExpenses[$key][(int) $item['expense_id']] = true;
        }
        foreach ($groups as $key => &$group) {
            $group['proposed'] = $group['totals']['current_planning']['official'];
            $group['actual'] = $group['totals']['actual']['official'];
            $group['unapproved_actual_expenses'] = count(array_diff_key($actualExpenses[$key] ?? [], $approvedExpenses[$key] ?? []));
            $group['residual'] = bcsub($group['approved'], $group['actual'], 2);
            $group['variance'] = bcsub($group['actual'], $group['approved'], 2);
            $group['utilization_percentage'] = $this->utilization($group['actual'], $group['approved']);
        }
        unset($group);

        return array_values($groups);
    }

    private function included(ProjectedEconomicLine $line, EconomicReportFilterData $filter): bool
    {
        return ($filter->costCenterId === null || $line->costCenterId === $filter->costCenterId)
            && ($filter->projectId === null || $line->projectId === $filter->projectId)
            && ($filter->contractId === null || $line->contractId === $filter->contractId)
            && ($filter->vendorId === null || $line->vendorId === $filter->vendorId);
    }

    /**
     * @param  array<string, array<int, string>>  $labels
     * @return array{string, string}
     */
    private function groupIdentity(ProjectedEconomicLine $line, string $groupBy, array $labels): array
    {
        return match ($groupBy) {
            'cost_center' => ['cost-center:'.$line->costCenterId, $line->costCenterName ?? '—'],
            'project' => [$line->projectId === null ? 'project:none' : 'project:'.$line->projectId, $line->projectId === null ? 'Senza progetto' : ($labels['project'][$line->projectId] ?? '—')],
            'contract' => [$line->contractId === null ? 'contract:none' : 'contract:'.$line->contractId, $line->contractId === null ? 'Senza contratto' : ($labels['contract'][$line->contractId] ?? '—')],
            'vendor' => [$line->vendorId === null ? 'vendor:none' : 'vendor:'.$line->vendorId, $line->vendorName ?? 'Senza fornitore'],
            'expense' => ['expense:'.$line->expenseId, $line->expenseTitle],
            default => throw new DomainException('INVALID_REPORT_GROUPING'),
        };
    }

    /** @return array<string, mixed> */
    private function group(string $key, string $label, string $groupBy, ProjectedEconomicLine $line, AnnualEconomicProjection $projection): array
    {
        return [
            'key' => $key, 'label' => $label, 'group_by' => $groupBy,
            'expense_id' => $groupBy === 'expense' ? $line->expenseId : null,
            'cost_center_id' => $groupBy === 'cost_center' ? $line->costCenterId : null,
            'project_id' => $groupBy === 'project' ? $line->projectId : null,
            'contract_id' => $groupBy === 'contract' ? $line->contractId : null,
            'vendor_id' => $groupBy === 'vendor' ? $line->vendorId : null,
            'currency' => $projection->currency, 'basis' => $projection->basis,
            'totals' => $this->zeroProjectionTotals(), 'approved' => '0.00',
            'unapproved_actual_expenses' => 0, 'plafond_expenses' => $line->expenseKind === 'plafond' ? 1 : 0,
            'lines' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function line(ProjectedEconomicLine $line): array
    {
        return [
            'expense_id' => $line->expenseId, 'row_id' => $line->rowId, 'type' => $line->type,
            'contributes_to_current_planning' => $line->contributesToCurrentPlanning,
            'amount' => $this->measure($line->amount),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function labels(TenantContext $context, int $planningYearId): array
    {
        $records = DB::table('expenses')
            ->leftJoin('projects', fn ($join) => $join->on('projects.id', '=', 'expenses.project_id')
                ->on('projects.tenant_id', '=', 'expenses.tenant_id'))
            ->leftJoin('contracts', fn ($join) => $join->on('contracts.id', '=', 'expenses.contract_id')
                ->on('contracts.tenant_id', '=', 'expenses.tenant_id'))
            ->where('expenses.tenant_id', $context->tenantId)->where('expenses.planning_year_id', $planningYearId)
            ->get(['expenses.project_id', 'projects.title as project_title', 'expenses.contract_id', 'contracts.title as contract_title']);

        return [
            'project' => $records->whereNotNull('project_id')->mapWithKeys(static fn (object $row): array => [(int) $row->project_id => (string) $row->project_title])->all(),
            'contract' => $records->whereNotNull('contract_id')->mapWithKeys(static fn (object $row): array => [(int) $row->contract_id => (string) $row->contract_title])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function approvalItems(TenantContext $context, EconomicReportFilterData $filter): array
    {
        $cutoff = $filter->asOf === null ? CarbonImmutable::now('UTC') : $this->cutoff($filter->asOf, $context->timezone);
        $approval = DB::table('budget_approvals')->where('tenant_id', $context->tenantId)
            ->where('planning_year_id', $filter->planningYearId)->where('recorded_at', '<=', $cutoff)
            ->where(fn ($query) => $query->whereNull('annulled_at')->orWhere('annulled_at', '>', $cutoff))
            ->orderByDesc('recorded_at')->orderByDesc('id')->value('id');
        if ($approval === null) {
            return [];
        }

        return DB::table('budget_approval_items')->where('tenant_id', $context->tenantId)->where('budget_approval_id', $approval)
            ->get(['expense_id', 'cost_center_id', 'project_id', 'contract_id', 'vendor_id', 'official_amount'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{string}
     */
    private function approvalIdentity(array $item, string $groupBy): array
    {
        return [match ($groupBy) {
            'cost_center' => 'cost-center:'.$item['cost_center_id'],
            'project' => $item['project_id'] === null ? 'project:none' : 'project:'.$item['project_id'],
            'contract' => $item['contract_id'] === null ? 'contract:none' : 'contract:'.$item['contract_id'],
            'vendor' => $item['vendor_id'] === null ? 'vendor:none' : 'vendor:'.$item['vendor_id'],
            'expense' => 'expense:'.$item['expense_id'],
            default => throw new DomainException('INVALID_REPORT_GROUPING'),
        }];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, array<string, string>>
     */
    private function sumProjectionTotals(array $groups, string $basis): array
    {
        return array_reduce($groups, fn (array $sum, array $group): array => [
            'current_planning' => $this->addArrays($sum['current_planning'], $group['totals']['current_planning'], $basis),
            'actual' => $this->addArrays($sum['actual'], $group['totals']['actual'], $basis),
        ], $this->zeroProjectionTotals());
    }

    /** @return array<string, array<string, string>> */
    private function zeroProjectionTotals(): array
    {
        $zero = ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00', 'official' => '0.00'];

        return ['current_planning' => $zero, 'actual' => $zero];
    }

    /**
     * @param  array<string, string>  $left
     * @return array<string, string>
     */
    private function addMeasure(array $left, EconomicMeasure $right, string $basis): array
    {
        return $this->addArrays($left, $this->measure($right), $basis);
    }

    /**
     * @param  array<string, string>  $left
     * @param  array<string, string>  $right
     * @return array<string, string>
     */
    private function addArrays(array $left, array $right, string $basis): array
    {
        foreach (['net', 'vat', 'gross'] as $component) {
            $left[$component] = bcadd($left[$component], $right[$component], 2);
        }
        $left['official'] = $left[$basis];

        return $left;
    }

    /** @return array{net:string,vat:string,gross:string,official:string} */
    private function measure(EconomicMeasure $measure): array
    {
        return ['net' => $measure->net, 'vat' => $measure->vat, 'gross' => $measure->gross, 'official' => $measure->official];
    }

    private function utilization(string $actual, string $approved): ?string
    {
        return bccomp($approved, '0', 2) === 1 ? bcdiv(bcmul($actual, '100', 4), $approved, 2) : null;
    }

    private function cutoff(string $value, string $timezone): CarbonImmutable
    {
        try {
            return (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1
                ? CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone)->endOfDay()
                : CarbonImmutable::parse($value, $timezone))->utc();
        } catch (\Throwable) {
            throw new DomainException('INVALID_HISTORY_CUTOFF');
        }
    }
}
