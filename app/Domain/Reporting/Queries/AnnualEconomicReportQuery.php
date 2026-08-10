<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Reporting\Data\EconomicReportFilterData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use DomainException;

final readonly class AnnualEconomicReportQuery
{
    public function __construct(private AnnualBudgetQuery $budgetQuery, private HistoricalAnnualBudgetQuery $historicalBudgetQuery) {}

    /** @return array<string, mixed> */
    public function execute(User $actor, TenantContext $context, EconomicReportFilterData $filter): array
    {
        if (! in_array($filter->groupBy, ['cost_center', 'project', 'contract', 'vendor', 'expense'], true)) {
            throw new DomainException('INVALID_REPORT_GROUPING');
        }
        if ($filter->state !== null && ! in_array($filter->state, ['open', 'closed'], true)) {
            throw new DomainException('INVALID_REPORT_STATE');
        }

        $budget = $filter->asOf === null
            ? $this->budgetQuery->execute($actor, $context, $filter->planningYearId)
            : $this->historicalBudgetQuery->execute($actor, $context, $filter->planningYearId, $filter->asOf);
        /** @var list<array<string, mixed>> $annualExpenses */
        $annualExpenses = $this->reconciledGroupLines($budget['expenses']);
        $expenses = array_values(array_filter(
            $annualExpenses,
            static fn (array $expense): bool => ($filter->costCenterId === null || $expense['cost_center_id'] === $filter->costCenterId)
                && ($filter->projectId === null || $expense['project_id'] === $filter->projectId)
                && ($filter->vendorId === null || $expense['vendor_id'] === $filter->vendorId)
                && ($filter->state === null || $expense['state'] === $filter->state),
        ));

        $summary = $this->summary($expenses, (string) $budget['summary']['currency'], (string) $budget['summary']['official_basis']);
        $resultGroups = $this->groups($expenses, $filter->groupBy);
        $visualization = $this->visualization($resultGroups, $summary);
        usort($resultGroups, static function (array $left, array $right): int {
            $labelComparison = strcasecmp((string) $left['label'], (string) $right['label']);

            return $labelComparison !== 0 ? $labelComparison : strcmp((string) $left['key'], (string) $right['key']);
        });

        $total = count($resultGroups);
        $lastPage = max(1, (int) ceil($total / $filter->perPage));
        $page = min(max($filter->page, 1), $lastPage);

        return [
            'data' => array_slice($resultGroups, ($page - 1) * $filter->perPage, $filter->perPage),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $filter->perPage,
                'total' => $total,
            ],
            'mode' => $budget['mode'],
            'requested_as_of' => $budget['requested_as_of'],
            'cutoff_utc' => $budget['cutoff_utc'],
            'read_only' => $budget['read_only'],
            'budget' => $budget['budget'],
            'summary' => $summary,
            'global_plafond_overrun' => (string) $budget['summary']['plafond_overrun'],
            'visualization' => $visualization,
            'filters' => [
                'planning_year_id' => $filter->planningYearId,
                'cost_center_id' => $filter->costCenterId,
                'project_id' => $filter->projectId,
                'vendor_id' => $filter->vendorId,
                'state' => $filter->state,
                'group_by' => $filter->groupBy,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @return list<array<string, mixed>>
     */
    private function groups(array $expenses, string $groupBy): array
    {
        /** @var array<string, array{key:string,label:string,group_by:string,proposed:string,approved:string,actual:string,open_expenses:int,closed_expenses:int,unapproved_actual_expenses:int,plafond_expenses:int}> $groups */
        $groups = [];
        foreach ($expenses as $expense) {
            [$key, $label] = $this->groupIdentity($expense, $groupBy);
            $groups[$key] ??= [
                'key' => $key,
                'label' => $label,
                'group_by' => $groupBy,
                'proposed' => '0.00',
                'approved' => '0.00',
                'actual' => '0.00',
                'open_expenses' => 0,
                'closed_expenses' => 0,
                'unapproved_actual_expenses' => 0,
                'plafond_expenses' => 0,
            ];
            if (! $this->excludedFromProposal($expense) && $expense['planned'] !== null) {
                $groups[$key]['proposed'] = bcadd($groups[$key]['proposed'], $expense['planned'], 2);
            }
            if ($expense['approved'] !== null) {
                $groups[$key]['approved'] = bcadd($groups[$key]['approved'], $expense['approved'], 2);
            }
            $groups[$key]['actual'] = bcadd($groups[$key]['actual'], $expense['actual'], 2);
            $expense['state'] === 'open' ? $groups[$key]['open_expenses']++ : $groups[$key]['closed_expenses']++;
            if ($expense['approved'] === null && $expense['has_actual']) {
                $groups[$key]['unapproved_actual_expenses']++;
            }
            if ($expense['kind'] === 'plafond') {
                $groups[$key]['plafond_expenses']++;
            }
        }

        return array_values(array_map(fn (array $group): array => $this->completeGroup($group), $groups));
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function completeGroup(array $group): array
    {
        $group['residual'] = bcsub($group['approved'], $group['actual'], 2);
        $group['variance'] = bcsub($group['actual'], $group['approved'], 2);
        $group['utilization_percentage'] = $this->utilization($group['actual'], $group['approved']);

        return $group;
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @return array<string, mixed>
     */
    private function summary(array $expenses, string $currency, string $officialBasis): array
    {
        $proposed = $approved = $actual = '0.00';
        $open = $closed = $unapproved = 0;
        foreach ($expenses as $expense) {
            if (! $this->excludedFromProposal($expense) && $expense['planned'] !== null) {
                $proposed = bcadd($proposed, $expense['planned'], 2);
            }
            if ($expense['approved'] !== null) {
                $approved = bcadd($approved, $expense['approved'], 2);
            }
            $actual = bcadd($actual, $expense['actual'], 2);
            $expense['state'] === 'open' ? $open++ : $closed++;
            if ($expense['approved'] === null && $expense['has_actual']) {
                $unapproved++;
            }
        }

        return [
            'currency' => $currency,
            'official_basis' => $officialBasis,
            'proposed' => $proposed,
            'approved_current' => $approved,
            'actual' => $actual,
            'residual' => bcsub($approved, $actual, 2),
            'variance' => bcsub($actual, $approved, 2),
            'utilization_percentage' => $this->utilization($actual, $approved),
            'open_expenses' => $open,
            'closed_expenses' => $closed,
            'unapproved_actual_expenses' => $unapproved,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function visualization(array $groups, array $summary): array
    {
        $ranked = $groups;
        usort($ranked, function (array $left, array $right): int {
            $magnitudeComparison = bccomp($this->groupMagnitude($right), $this->groupMagnitude($left), 2);
            if ($magnitudeComparison !== 0) {
                return $magnitudeComparison;
            }
            $labelComparison = strcasecmp((string) $left['label'], (string) $right['label']);

            return $labelComparison !== 0 ? $labelComparison : strcmp((string) $left['key'], (string) $right['key']);
        });
        $visualizationGroups = array_map(static fn (array $group): array => [
            'key' => $group['key'],
            'label' => $group['label'],
            'proposed' => $group['proposed'],
            'approved' => $group['approved'],
            'actual' => $group['actual'],
            'residual' => $group['residual'],
            'variance' => $group['variance'],
            'utilization_percentage' => $group['utilization_percentage'],
        ], array_slice($ranked, 0, 10));

        $proposed = array_values(array_filter($groups, static fn (array $group): bool => bccomp($group['proposed'], '0.00', 2) === 1));
        usort($proposed, static function (array $left, array $right): int {
            $amountComparison = bccomp($right['proposed'], $left['proposed'], 2);
            if ($amountComparison !== 0) {
                return $amountComparison;
            }
            $labelComparison = strcasecmp((string) $left['label'], (string) $right['label']);

            return $labelComparison !== 0 ? $labelComparison : strcmp((string) $left['key'], (string) $right['key']);
        });
        $breakdown = array_map(static fn (array $group): array => [
            'key' => $group['key'],
            'label' => $group['label'],
            'proposed' => $group['proposed'],
        ], array_slice($proposed, 0, 5));
        $other = array_reduce(
            array_slice($proposed, 5),
            static fn (string $sum, array $group): string => bcadd($sum, $group['proposed'], 2),
            '0.00',
        );
        if (bccomp($other, '0.00', 2) === 1) {
            $breakdown[] = ['key' => 'other', 'label' => 'Altri', 'proposed' => $other];
        }

        return [
            'groups' => $visualizationGroups,
            'proposed_breakdown' => $breakdown,
            'expense_states' => [
                'open' => $summary['open_expenses'],
                'closed' => $summary['closed_expenses'],
                'total' => $summary['open_expenses'] + $summary['closed_expenses'],
            ],
        ];
    }

    /** @param array<string, mixed> $group */
    private function groupMagnitude(array $group): string
    {
        return $this->maximum(
            $this->absolute((string) $group['proposed']),
            $this->maximum($this->absolute((string) $group['approved']), $this->absolute((string) $group['actual'])),
        );
    }

    private function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }

    private function utilization(string $actual, string $approved): ?string
    {
        return bccomp($approved, '0', 2) === 1
            ? bcdiv(bcmul($actual, '100', 4), $approved, 2)
            : null;
    }

    /**
     * @param  array<string, mixed>  $expense
     * @return array{string, string}
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
     * Keep report partitions cent-identical to the annual Budget by assigning
     * covered consumption against each Plafond line before any Report filter.
     *
     * @param  list<array<string, mixed>>  $expenses
     * @return list<array<string, mixed>>
     */
    private function reconciledGroupLines(array $expenses): array
    {
        $indexById = [];
        foreach ($expenses as $index => $expense) {
            $indexById[(int) $expense['id']] = $index;
        }
        $consumersByPlafond = [];
        foreach ($expenses as $expense) {
            if ($expense['funded_plafond_expense_id'] !== null) {
                $consumersByPlafond[(int) $expense['funded_plafond_expense_id']][] = $expense;
            }
        }
        foreach ($consumersByPlafond as $plafondId => $consumers) {
            $plafondIndex = $indexById[$plafondId] ?? null;
            if ($plafondIndex === null || $expenses[$plafondIndex]['kind'] !== 'plafond') {
                throw new DomainException('TENANT_RELATION_MISMATCH');
            }
            $plafond = $expenses[$plafondIndex];
            if (! $this->excludedFromProposal($plafond) && $plafond['planned'] !== null) {
                $consumerPlanned = array_reduce(
                    $consumers,
                    fn (string $sum, array $consumer): string => $this->excludedFromProposal($consumer)
                        ? $sum
                        : bcadd($sum, $consumer['planned'] ?? '0.00', 2),
                    '0.00',
                );
                $expenses[$plafondIndex]['planned'] = bcsub(
                    $plafond['planned'],
                    $this->minimum($plafond['planned'], $consumerPlanned),
                    2,
                );
            }
            if ($plafond['approved'] !== null) {
                $consumerApproved = array_reduce(
                    $consumers,
                    static fn (string $sum, array $consumer): string => bcadd($sum, $consumer['approved'] ?? '0.00', 2),
                    '0.00',
                );
                $expenses[$plafondIndex]['approved'] = bcsub(
                    $plafond['approved'],
                    $this->minimum($plafond['approved'], $consumerApproved),
                    2,
                );
            }
        }

        return $expenses;
    }

    /** @param array<string, mixed> $expense */
    private function excludedFromProposal(array $expense): bool
    {
        return $expense['state'] === 'closed'
            && in_array($expense['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true);
    }

    private function minimum(string $left, string $right): string
    {
        return bccomp($left, $right, 2) <= 0 ? $left : $right;
    }

    private function maximum(string $left, string $right): string
    {
        return bccomp($left, $right, 2) >= 0 ? $left : $right;
    }
}
