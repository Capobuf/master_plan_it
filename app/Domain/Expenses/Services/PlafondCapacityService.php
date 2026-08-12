<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Economics\Data\AnnualEconomicProjection;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Economics\Data\ProjectedEconomicLine;
use App\Domain\Economics\Services\MoneyCalculator;
use App\Domain\Expenses\Data\PlafondInsufficiency;
use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use DomainException;

final class PlafondCapacityService
{
    public function impact(
        PlafondEconomicProjection $current,
        PlafondEconomicProjection $proposed,
        string $requested,
    ): PlafondImpact {
        if ($current->plafondExpenseId !== $proposed->plafondExpenseId
            || $current->currency !== $proposed->currency
            || $current->basis !== $proposed->basis) {
            throw new DomainException('ECONOMIC_RECONCILIATION_FAILED');
        }

        $money = new MoneyCalculator;
        $requested = $money->normalize($requested);
        $shortage = $money->subtract($proposed->consumed->official, $proposed->allocation->official);
        if (bccomp($shortage, '0.00', MoneyCalculator::SCALE) < 0) {
            $shortage = '0.00';
        }
        $blockingRows = array_values(array_filter(
            $proposed->coveredLines,
            static fn (ProjectedEconomicLine $line): bool => $line->contributesToConsumed,
        ));
        usort(
            $blockingRows,
            static fn (ProjectedEconomicLine $left, ProjectedEconomicLine $right): int => $left->rowId <=> $right->rowId,
        );

        return new PlafondImpact(
            $current,
            $proposed,
            $requested,
            $shortage,
            bccomp($shortage, '0.00', MoneyCalculator::SCALE) === 0,
            $blockingRows,
        );
    }

    public function assertSufficient(
        PlafondImpact $impact,
        string $field,
        ?string $allocated = null,
        ?string $available = null,
        ?string $required = null,
    ): void {
        if ($impact->canConfirm) {
            return;
        }

        throw new PlafondInsufficientException(new PlafondInsufficiency(
            $impact->proposed->plafondExpenseId,
            $impact->proposed->currency,
            $impact->proposed->basis,
            $allocated ?? $impact->current->allocation->official,
            $available ?? $impact->current->available->official,
            $required ?? $impact->requested,
            $impact->shortage,
            $field,
            $impact,
        ));
    }

    /**
     * @param  array<int, PlafondEconomicProjection>  $currentPlafonds
     */
    public function assertAnnualProjectionSufficient(
        AnnualEconomicProjection $proposed,
        array $currentPlafonds = [],
        string $field = 'economic_basis',
    ): void {
        $insufficiencies = $this->insufficiencies($proposed, $currentPlafonds, $field);
        if ($insufficiencies !== []) {
            throw new PlafondInsufficientException($insufficiencies[0]);
        }
    }

    /**
     * @param  array<int, PlafondEconomicProjection>  $currentPlafonds
     * @return list<PlafondInsufficiency>
     */
    public function insufficiencies(
        AnnualEconomicProjection $proposed,
        array $currentPlafonds = [],
        string $field = 'economic_basis',
    ): array {
        $plafonds = $proposed->plafonds;
        ksort($plafonds, SORT_NUMERIC);
        $insufficiencies = [];

        foreach ($plafonds as $plafondId => $projection) {
            $current = $currentPlafonds[$plafondId] ?? $projection;
            $impact = $this->impact($current, $projection, $projection->consumed->official);
            if (! $impact->canConfirm) {
                $insufficiencies[] = new PlafondInsufficiency(
                    $projection->plafondExpenseId,
                    $projection->currency,
                    $projection->basis,
                    $projection->allocation->official,
                    $current->available->official,
                    $projection->consumed->official,
                    $impact->shortage,
                    $field,
                    $impact,
                );
            }
        }

        return $insufficiencies;
    }
}
