<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use DomainException;

final readonly class AnnualEconomicReportQuery
{
    public function __construct(
        private AnnualBudgetQuery $budgetQuery,
        private HistoricalAnnualBudgetQuery $historicalBudgetQuery,
    ) {}

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, EconomicReportFilterData $filter): array
    {
        if (! in_array($filter->groupBy, ['cost_center', 'project', 'contract', 'vendor', 'expense'], true)) {
            throw new DomainException('INVALID_REPORT_GROUPING');
        }

        $budget = $filter->asOf === null
            ? $this->budgetQuery->execute($actor, $context, $filter->planningYearId)
            : $this->historicalBudgetQuery->execute($actor, $context, $filter->planningYearId, $filter->asOf);
        /** @var list<array<string, mixed>> $expenses */
        $expenses = array_values(array_filter(
            $budget['expenses'],
            static fn (array $expense): bool => ($filter->costCenterId === null || $expense['cost_center_id'] === $filter->costCenterId)
                && ($filter->projectId === null || $expense['project_id'] === $filter->projectId)
                && ($filter->contractId === null || $expense['contract_id'] === $filter->contractId)
                && ($filter->vendorId === null || $expense['vendor_id'] === $filter->vendorId),
        ));

        $groups = $this->groups($expenses, $filter->groupBy, (string) $budget['currency'], (string) $budget['basis']);
        usort($groups, static function (array $left, array $right): int {
            $label = strcasecmp((string) $left['label'], (string) $right['label']);

            return $label !== 0 ? $label : strcmp((string) $left['key'], (string) $right['key']);
        });
        $totals = $this->sumProjectionTotals($groups, (string) $budget['basis']);
        $approved = array_reduce($groups, static fn (string $sum, array $group): string => bcadd($sum, (string) $group['approved'], 2), '0.00');
        $unapproved = array_reduce($groups, static fn (int $sum, array $group): int => $sum + (int) $group['unapproved_actual_expenses'], 0);
        $total = count($groups);
        $lastPage = max(1, (int) ceil($total / $filter->perPage));
        $page = min(max($filter->page, 1), $lastPage);

        return [
            'data' => array_slice($groups, ($page - 1) * $filter->perPage, $filter->perPage),
            'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => $filter->perPage, 'total' => $total],
            'mode' => $budget['mode'],
            'requested_as_of' => $budget['requested_as_of'],
            'cutoff_utc' => $budget['cutoff_utc'],
            'read_only' => $budget['read_only'],
            'budget' => $budget['budget'],
            'currency' => $budget['currency'],
            'basis' => $budget['basis'],
            'totals' => $totals,
            'summary' => [
                'currency' => $budget['currency'],
                'official_basis' => $budget['basis'],
                'proposed' => $totals['current_planning']['official'],
                'approved_current' => $approved,
                'actual' => $totals['actual']['official'],
                'residual' => bcsub($approved, $totals['actual']['official'], 2),
                'variance' => bcsub($totals['actual']['official'], $approved, 2),
                'utilization_percentage' => $this->utilization($totals['actual']['official'], $approved),
                'plafond_overrun' => '0.00',
                'unapproved_actual_expenses' => $unapproved,
            ],
            'global_plafond_overrun' => '0.00',
            'filters' => [
                'planning_year_id' => $filter->planningYearId,
                'cost_center_id' => $filter->costCenterId,
                'project_id' => $filter->projectId,
                'contract_id' => $filter->contractId,
                'vendor_id' => $filter->vendorId,
                'group_by' => $filter->groupBy,
                'as_of' => $filter->asOf,
            ],
        ];
    }

    /**
     * This query consumes the serialized view of AnnualEconomicProjection produced by
     * the Budget query. It never reloads or recalculates ExpenseRow money.
     *
     * @param  list<array<string, mixed>>  $expenses
     * @return list<array<string, mixed>>
     */
    private function groups(array $expenses, string $groupBy, string $currency, string $basis): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        foreach ($expenses as $expense) {
            [$key, $label] = $this->groupIdentity($expense, $groupBy);
            $groups[$key] ??= [
                'key' => $key,
                'label' => $label,
                'group_by' => $groupBy,
                'expense_id' => $groupBy === 'expense' ? $expense['id'] : null,
                'cost_center_id' => $groupBy === 'cost_center' ? $expense['cost_center_id'] : null,
                'project_id' => $groupBy === 'project' ? $expense['project_id'] : null,
                'contract_id' => $groupBy === 'contract' ? $expense['contract_id'] : null,
                'vendor_id' => $groupBy === 'vendor' ? $expense['vendor_id'] : null,
                'currency' => $currency,
                'basis' => $basis,
                'totals' => $this->zeroProjectionTotals(),
                'approved' => '0.00',
                'unapproved_actual_expenses' => 0,
                'plafond_expenses' => 0,
                'lines' => [],
            ];
            $groups[$key]['totals'] = $this->addProjectionTotals($groups[$key]['totals'], $expense['totals'], $basis);
            $groups[$key]['approved'] = bcadd($groups[$key]['approved'], (string) ($expense['approved'] ?? '0.00'), 2);
            if (($expense['approved'] ?? null) === null && ($expense['has_actual'] ?? false)) {
                $groups[$key]['unapproved_actual_expenses']++;
            }
            if ($expense['kind'] === 'plafond') {
                $groups[$key]['plafond_expenses']++;
            }
            $groups[$key]['lines'] = [...$groups[$key]['lines'], ...($expense['lines'] ?? [])];
        }

        return array_values(array_map(function (array $group): array {
            $group['proposed'] = $group['totals']['current_planning']['official'];
            $group['actual'] = $group['totals']['actual']['official'];
            $group['residual'] = bcsub($group['approved'], $group['actual'], 2);
            $group['variance'] = bcsub($group['actual'], $group['approved'], 2);
            $group['utilization_percentage'] = $this->utilization($group['actual'], $group['approved']);

            return $group;
        }, $groups));
    }

    /**
     * @param  array<string, mixed>  $expense
     * @return array{0: string, 1: string}
     */
    private function groupIdentity(array $expense, string $groupBy): array
    {
        return match ($groupBy) {
            'cost_center' => ['cost-center:'.$expense['cost_center_id'], (string) $expense['cost_center_name']],
            'project' => [$expense['project_id'] === null ? 'project:none' : 'project:'.$expense['project_id'], $expense['project_title'] ?? 'Senza progetto'],
            'contract' => [$expense['contract_id'] === null ? 'contract:none' : 'contract:'.$expense['contract_id'], $expense['contract_title'] ?? 'Senza contratto'],
            'vendor' => [$expense['vendor_id'] === null ? 'vendor:none' : 'vendor:'.$expense['vendor_id'], $expense['vendor_name'] ?? 'Senza fornitore'],
            'expense' => ['expense:'.$expense['id'], (string) $expense['title']],
            default => throw new DomainException('INVALID_REPORT_GROUPING'),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array<string, array<string, string>>
     */
    private function sumProjectionTotals(array $groups, string $basis): array
    {
        return array_reduce(
            $groups,
            fn (array $sum, array $group): array => $this->addProjectionTotals($sum, $group['totals'], $basis),
            $this->zeroProjectionTotals(),
        );
    }

    /** @return array<string, array<string, string>> */
    private function zeroProjectionTotals(): array
    {
        $zero = ['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00', 'official' => '0.00'];

        return ['current_planning' => $zero, 'actual' => $zero];
    }

    /**
     * @param  array<string, array<string, string>>  $left
     * @param  array<string, array<string, string>>  $right
     * @return array<string, array<string, string>>
     */
    private function addProjectionTotals(array $left, array $right, string $basis): array
    {
        foreach (['current_planning', 'actual'] as $bucket) {
            foreach (['net', 'vat', 'gross'] as $component) {
                $left[$bucket][$component] = bcadd($left[$bucket][$component], $right[$bucket][$component], 2);
            }
            $left[$bucket]['official'] = $left[$bucket][$basis];
        }

        return $left;
    }

    private function utilization(string $actual, string $approved): ?string
    {
        return bccomp($approved, '0', 2) === 1
            ? bcdiv(bcmul($actual, '100', 4), $approved, 2)
            : null;
    }
}
