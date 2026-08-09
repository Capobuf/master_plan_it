<?php

namespace App\Domain\Reporting\Queries;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use DomainException;

final readonly class AnnualEconomicReportQuery
{
    public function __construct(private AnnualBudgetQuery $budgetQuery, private HistoricalAnnualBudgetQuery $historicalBudgetQuery) {}

    /** @return array<string, mixed> */
    public function execute(
        User $actor,
        TenantContext $context,
        int $planningYearId,
        ?int $costCenterId,
        string $groupBy,
        int $page,
        int $perPage,
        ?string $asOf = null,
    ): array {
        if (! in_array($groupBy, ['cost_center', 'project', 'contract', 'vendor', 'expense'], true)) {
            throw new DomainException('INVALID_REPORT_GROUPING');
        }
        $budget = $asOf === null
            ? $this->budgetQuery->execute($actor, $context, $planningYearId, $costCenterId)
            : $this->historicalBudgetQuery->execute($actor, $context, $planningYearId, $asOf, $costCenterId);
        /** @var list<array<string, mixed>> $expenses */
        $expenses = $this->reconciledGroupLines($budget['expenses']);
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
            $excluded = $expense['state'] === 'closed' && in_array($expense['closure_outcome'], ['not_incurred', 'cancelled', 'moved'], true);
            if (! $excluded && $expense['planned'] !== null) {
                $groups[$key]['proposed'] = bcadd($groups[$key]['proposed'], $expense['planned'], 2);
            }
            if ($expense['approved'] !== null) {
                $groups[$key]['approved'] = bcadd($groups[$key]['approved'], $expense['approved'], 2);
            }
            $groups[$key]['actual'] = bcadd($groups[$key]['actual'], $expense['actual'], 2);
            if ($expense['state'] === 'open') {
                $groups[$key]['open_expenses']++;
            } else {
                $groups[$key]['closed_expenses']++;
            }
            if ($expense['approved'] === null && $expense['has_actual']) {
                $groups[$key]['unapproved_actual_expenses']++;
            }
            if ($expense['kind'] === 'plafond') {
                $groups[$key]['plafond_expenses']++;
            }
        }
        /** @var list<array<string, mixed>> $resultGroups */
        $resultGroups = [];
        foreach ($groups as $group) {
            $group['residual'] = bcsub($group['approved'], $group['actual'], 2);
            $group['variance'] = bcsub($group['actual'], $group['approved'], 2);
            $group['utilization_percentage'] = bccomp($group['approved'], '0', 2) === 1
                ? bcdiv(bcmul($group['actual'], '100', 4), $group['approved'], 2)
                : null;
            $resultGroups[] = $group;
        }
        usort($resultGroups, static fn (array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));
        $total = count($resultGroups);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max($page, 1), $lastPage);

        return [
            'data' => array_slice($resultGroups, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'mode' => $budget['mode'],
            'requested_as_of' => $budget['requested_as_of'],
            'cutoff_utc' => $budget['cutoff_utc'],
            'read_only' => $budget['read_only'],
            'budget' => $budget['budget'],
            'summary' => $budget['summary'],
            'filters' => [
                'planning_year_id' => $planningYearId,
                'cost_center_id' => $costCenterId,
                'group_by' => $groupBy,
            ],
        ];
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
     * Keep group totals cent-identical to the shared Budget summary by assigning
     * covered consumption against the Plafond line before grouping.
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
            $plafondExcluded = $this->excludedFromProposal($plafond);
            if (! $plafondExcluded && $plafond['planned'] !== null) {
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
}
