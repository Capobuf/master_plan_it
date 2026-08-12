<?php

namespace App\Domain\Economics\Services;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ExpenseEconomicProjection;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use DomainException;

final class EconomicEngine
{
    public function project(EconomicDataset $dataset): AnnualEconomicProjection
    {
        $basis = $dataset->scope->budgetBasis->value;
        $zero = EconomicMeasure::zero($basis);
        $planning = $zero;
        $actual = $zero;
        /** @var array<int, array{current:?int,planning:EconomicMeasure,actual:EconomicMeasure,lines:list<ProjectedEconomicLine>}> $grouped */
        $grouped = [];
        /** @var array<int, array{title:string,cost_center_id:int,cost_center_name:string,allocation:EconomicMeasure,coverage:EconomicMeasure,consumed:EconomicMeasure,allocation_lines:list<ProjectedEconomicLine>,covered_lines:list<ProjectedEconomicLine>}> $plafonds */
        $plafonds = [];
        foreach ($dataset->lines as $line) {
            if ($line->expenseKind !== 'plafond') {
                continue;
            }
            if ($line->type !== 'allocation_adjustment' || $line->fundedPlafondExpenseId !== null) {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $plafonds[$line->expenseId] ??= [
                'title' => $line->expenseTitle,
                'cost_center_id' => $line->costCenterId,
                'cost_center_name' => $line->costCenterName,
                'allocation' => $zero,
                'coverage' => $zero,
                'consumed' => $zero,
                'allocation_lines' => [],
                'covered_lines' => [],
            ];
        }
        $lines = [];
        foreach ($dataset->lines as $line) {
            if ($line->expenseKind === 'ordinary' && $line->type === 'allocation_adjustment') {
                throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
            }
            $measure = EconomicMeasure::fromAmounts($line->net, $line->vat, $line->gross, $basis);
            $isAllocation = $line->expenseKind === 'plafond';
            $coveredPlanning = $line->expenseKind === 'ordinary'
                && in_array($line->type, ['estimate', 'quote'], true)
                && $line->isCurrentPlanning
                && $line->fundedPlafondExpenseId !== null;
            $coveredActual = $line->expenseKind === 'ordinary'
                && $line->type === 'actual'
                && $line->fundedPlafondExpenseId !== null;
            $current = $isAllocation || ($line->type !== 'actual' && $line->isCurrentPlanning);
            $annualCurrent = $current && ! $coveredPlanning;
            $projected = new ProjectedEconomicLine(
                $line->expenseId,
                $line->rowId,
                $dataset->scope->planningYearId,
                $dataset->scope->yearLabel,
                $line->type,
                $line->isCurrentPlanning,
                $annualCurrent,
                $line->spendDate,
                $measure,
                $line->description,
                $line->notes,
                $line->costCenterId,
                $line->vendorId,
                $line->vendorName,
                $line->projectId,
                $line->contractId,
                $line->expenseKind,
                $line->expenseTitle,
                $line->costCenterName,
                $line->fundedPlafondExpenseId,
                $line->fundedPlafondTitle,
                $line->fundedPlafondCostCenterId,
                $line->fundedPlafondCostCenterName,
                $coveredPlanning,
                $coveredActual,
                $line->createdByUserId,
                $line->createdByUserName,
            );
            $entry = $grouped[$line->expenseId] ?? ['current' => null, 'planning' => $zero, 'actual' => $zero, 'lines' => []];
            if ($current) {
                $entry['current'] = $isAllocation ? null : $line->rowId;
                $entry['planning'] = $entry['planning']->plus($measure, $basis);
                if ($annualCurrent) {
                    $planning = $planning->plus($measure, $basis);
                }
            }
            if ($line->expenseKind === 'ordinary' && $line->type === 'actual') {
                $entry['actual'] = $entry['actual']->plus($measure, $basis);
                $actual = $actual->plus($measure, $basis);
            }
            if ($isAllocation) {
                $plafonds[$line->expenseId]['allocation'] = $plafonds[$line->expenseId]['allocation']->plus($measure, $basis);
                $plafonds[$line->expenseId]['allocation_lines'][] = $projected;
            } elseif ($line->fundedPlafondExpenseId !== null) {
                if (! isset($plafonds[$line->fundedPlafondExpenseId])) {
                    throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
                }
                if ($coveredPlanning) {
                    $plafonds[$line->fundedPlafondExpenseId]['coverage'] = $plafonds[$line->fundedPlafondExpenseId]['coverage']->plus($measure, $basis);
                }
                if ($coveredActual) {
                    $plafonds[$line->fundedPlafondExpenseId]['consumed'] = $plafonds[$line->fundedPlafondExpenseId]['consumed']->plus($measure, $basis);
                }
                $plafonds[$line->fundedPlafondExpenseId]['covered_lines'][] = $projected;
            }
            $entry['lines'][] = $projected;
            $grouped[$line->expenseId] = $entry;
            $lines[] = $projected;
        }
        $expenses = [];
        foreach ($grouped as $expenseId => $entry) {
            $expenses[$expenseId] = new ExpenseEconomicProjection($expenseId, $entry['current'], $entry['planning'], $entry['actual'], $entry['lines']);
        }

        $plafondProjections = [];
        foreach ($plafonds as $expenseId => $entry) {
            $plafondProjections[$expenseId] = new PlafondEconomicProjection(
                $expenseId,
                $dataset->scope->planningYearId,
                $entry['title'],
                $entry['cost_center_id'],
                $entry['cost_center_name'],
                $dataset->scope->currency,
                $basis,
                $entry['allocation'],
                $entry['coverage'],
                $entry['consumed'],
                $entry['allocation']->minus($entry['consumed'], $basis),
                $entry['allocation_lines'],
                $entry['covered_lines'],
            );
        }

        return new AnnualEconomicProjection($dataset->scope->tenantId, $dataset->scope->planningYearId, $dataset->scope->yearLabel, $dataset->scope->currency, $basis, $planning, $actual, $expenses, $lines, $plafondProjections);
    }
}
