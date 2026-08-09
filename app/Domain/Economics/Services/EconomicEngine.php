<?php

namespace App\Domain\Economics\Services;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicLine;
use App\Domain\Economics\Data\EconomicSummary;
use App\Domain\Money\Money;
use App\Domain\Money\Services\MonthlyAllocator;
use DateTimeImmutable;

final class EconomicEngine
{
    /** @return array{summary:EconomicSummary,monthly:array<string,string>,byType:array<string,string>,byCostCenter:array<string,string>} */
    public function calculate(EconomicDataset $dataset): array
    {
        $basis = $dataset->scope->budgetBasis->value;
        $amounts = array_fill_keys(['officialCurrentPosition', 'primary', 'proposed', 'idea', 'excluded', 'potential', 'net', 'vat', 'gross', 'planned', 'estimate', 'quote', 'actual', 'actualToConfirm', 'actualConfirmed', 'extra', 'plafondAllocated', 'plafondConsumed', 'plafondResidual', 'plafondOverrun'], '0.00');
        $monthly = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthly[sprintf('%04d-%02d', $dataset->scope->yearLabel, $month)] = '0.00';
        }
        $byType = ['estimate' => '0.00', 'quote' => '0.00', 'actual' => '0.00'];
        $byCostCenter = [];
        $plafondGroups = [];
        $consumedGroups = [];
        $fundedLines = [];
        foreach ($dataset->lines as $line) {
            $official = $basis === 'gross' ? $line->gross : $line->net;
            $bucket = $this->classify($line);
            $amounts['net'] = bcadd($amounts['net'], $line->net, 2);
            $amounts['vat'] = bcadd($amounts['vat'], $line->vat, 2);
            $amounts['gross'] = bcadd($amounts['gross'], $line->gross, 2);
            if ($line->fundedPlafondExpenseId === null) {
                $amounts[$bucket] = bcadd($amounts[$bucket], $official, 2);
                if ($bucket === 'primary') {
                    $amounts['officialCurrentPosition'] = bcadd($amounts['officialCurrentPosition'], $official, 2);
                }
            }
            $amounts[$line->type] = bcadd($amounts[$line->type], $official, 2);
            if (in_array($line->type, ['estimate', 'quote'], true)) {
                $amounts['planned'] = bcadd($amounts['planned'], $official, 2);
            }
            $byType[$line->type] = bcadd($byType[$line->type], $official, 2);
            if ($line->fundedPlafondExpenseId === null && $bucket === 'primary') {
                $byCostCenter[$line->costCenterName] = bcadd($byCostCenter[$line->costCenterName] ?? '0.00', $official, 2);
            }
            if ($line->isExtra) {
                $amounts['extra'] = bcadd($amounts['extra'], $official, 2);
            }
            if ($line->expenseKind === 'plafond') {
                $amounts['plafondAllocated'] = bcadd($amounts['plafondAllocated'], $official, 2);
                $plafondGroups[$line->expenseId] ??= ['official' => '0.00', 'net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
                $plafondGroups[$line->expenseId]['official'] = bcadd($plafondGroups[$line->expenseId]['official'], $official, 2);
                foreach (['net', 'vat', 'gross'] as $dimension) {
                    $plafondGroups[$line->expenseId][$dimension] = bcadd($plafondGroups[$line->expenseId][$dimension], $line->{$dimension}, 2);
                }
            }
            if ($line->fundedPlafondExpenseId !== null) {
                $amounts['plafondConsumed'] = bcadd($amounts['plafondConsumed'], $official, 2);
                $group = $line->fundedPlafondExpenseId;
                $consumedGroups[$group] ??= ['official' => '0.00', 'net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
                $consumedGroups[$group]['official'] = bcadd($consumedGroups[$group]['official'], $official, 2);
                foreach (['net', 'vat', 'gross'] as $dimension) {
                    $consumedGroups[$group][$dimension] = bcadd($consumedGroups[$group][$dimension], $line->{$dimension}, 2);
                }
                $fundedLines[$group] = $line;
            }
            foreach ($line->fundedPlafondExpenseId === null && $bucket === 'primary' ? $this->monthly($dataset, $line->spendDate, $line->periodStart, $line->periodEnd, $line->distribution, $official) : [] as $key => $value) {
                if (isset($monthly[$key])) {
                    $monthly[$key] = bcadd($monthly[$key], $value, 2);
                }
            }
        }
        $amounts['plafondResidual'] = '0.00';
        $amounts['plafondOverrun'] = '0.00';
        foreach (array_unique([...array_keys($plafondGroups), ...array_keys($consumedGroups)]) as $group) {
            $allocation = $plafondGroups[$group] ?? ['official' => '0.00', 'net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
            $consumption = $consumedGroups[$group] ?? ['official' => '0.00', 'net' => '0.00', 'vat' => '0.00', 'gross' => '0.00'];
            $difference = bcsub($allocation['official'], $consumption['official'], 2);
            if (bccomp($difference, '0', 2) >= 0) {
                $amounts['plafondResidual'] = bcadd($amounts['plafondResidual'], $difference, 2);
            } else {
                $overrun = ltrim($difference, '-');
                $amounts['plafondOverrun'] = bcadd($amounts['plafondOverrun'], $overrun, 2);
                $amounts['officialCurrentPosition'] = bcadd($amounts['officialCurrentPosition'], $overrun, 2);
                $amounts['primary'] = bcadd($amounts['primary'], $overrun, 2);
                $line = $fundedLines[$group] ?? null;
                if ($line !== null) {
                    $byCostCenter[$line->costCenterName] = bcadd($byCostCenter[$line->costCenterName] ?? '0.00', $overrun, 2);
                    foreach ($this->monthly($dataset, $line->spendDate, $line->periodStart, $line->periodEnd, $line->distribution, $overrun) as $key => $value) {
                        if (isset($monthly[$key])) {
                            $monthly[$key] = bcadd($monthly[$key], $value, 2);
                        }
                    }
                }
            }
            foreach (['net', 'vat', 'gross'] as $dimension) {
                $covered = bccomp($allocation[$dimension], $consumption[$dimension], 2) <= 0 ? $allocation[$dimension] : $consumption[$dimension];
                $amounts[$dimension] = bcsub($amounts[$dimension], $covered, 2);
            }
        }

        $amounts['potential'] = bcadd(bcadd($amounts['primary'], $amounts['proposed'], 2), $amounts['idea'], 2);

        return ['summary' => new EconomicSummary($basis, $amounts), 'monthly' => $monthly, 'byType' => $byType, 'byCostCenter' => $byCostCenter];
    }

    public function classify(EconomicLine $line): string
    {
        return 'primary';
    }

    /** @return array<string,string> */
    private function monthly(EconomicDataset $dataset, ?string $spend, ?string $start, ?string $end, ?string $distribution, string $amount): array
    {
        if ($spend !== null) {
            return [substr($spend, 0, 7) => $amount];
        }
        if ($start === null || $end === null || $distribution === null) {
            return [];
        }

        return array_map(fn (Money $money) => $money->amount(), (new MonthlyAllocator)->allocate(Money::fromDecimal($amount, $dataset->scope->currency, 2), new DateTimeImmutable($start), new DateTimeImmutable($end), $distribution));
    }
}
