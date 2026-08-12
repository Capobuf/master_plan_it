<?php

namespace App\Domain\Economics\Services;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\ExpenseEconomicProjection;
use App\Domain\Economics\Data\ProjectedEconomicLine;

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
        $lines = [];
        foreach ($dataset->lines as $line) {
            $measure = EconomicMeasure::fromAmounts($line->net, $line->vat, $line->gross, $basis);
            $current = $line->type !== 'actual' && $line->isCurrentPlanning;
            $projected = new ProjectedEconomicLine(
                $line->expenseId,
                $line->rowId,
                $dataset->scope->planningYearId,
                $dataset->scope->yearLabel,
                $line->type,
                $line->isCurrentPlanning,
                $current,
                $line->spendDate,
                $measure,
                $line->description,
                $line->notes,
                $line->costCenterId,
                $line->vendorId,
                $line->vendorName,
                $line->projectId,
                $line->contractId,
            );
            $entry = $grouped[$line->expenseId] ?? ['current' => null, 'planning' => $zero, 'actual' => $zero, 'lines' => []];
            if ($current) {
                $entry['current'] = $line->rowId;
                $entry['planning'] = $entry['planning']->plus($measure, $basis);
                $planning = $planning->plus($measure, $basis);
            }
            if ($line->type === 'actual') {
                $entry['actual'] = $entry['actual']->plus($measure, $basis);
                $actual = $actual->plus($measure, $basis);
            }
            $entry['lines'][] = $projected;
            $grouped[$line->expenseId] = $entry;
            $lines[] = $projected;
        }
        $expenses = [];
        foreach ($grouped as $expenseId => $entry) {
            $expenses[$expenseId] = new ExpenseEconomicProjection($expenseId, $entry['current'], $entry['planning'], $entry['actual'], $entry['lines']);
        }

        return new AnnualEconomicProjection($dataset->scope->tenantId, $dataset->scope->planningYearId, $dataset->scope->yearLabel, $dataset->scope->currency, $basis, $planning, $actual, $expenses, $lines);
    }
}
