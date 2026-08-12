<?php

namespace App\Domain\Plafonds\Data;

use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Economics\Data\ProjectedEconomicLine;

final class PlafondProjectionSerializer
{
    /** @return array{net: string, vat: string, gross: string, official: string} */
    public static function measure(EconomicMeasure $measure): array
    {
        return [
            'net' => $measure->net,
            'vat' => $measure->vat,
            'gross' => $measure->gross,
            'official' => $measure->official,
        ];
    }

    /** @return array<string, array{net: string, vat: string, gross: string, official: string}> */
    public static function measures(PlafondEconomicProjection $projection): array
    {
        return [
            'allocation' => self::measure($projection->allocation),
            'coverage_planned' => self::measure($projection->coveragePlanned),
            'consumed' => self::measure($projection->consumed),
            'available' => self::measure($projection->available),
        ];
    }

    /** @return array<string, mixed> */
    public static function coveredLine(ProjectedEconomicLine $line): array
    {
        return [
            'expense_id' => $line->expenseId,
            'expense_title' => $line->expenseTitle,
            'row_id' => $line->rowId,
            'description' => $line->description,
            'type' => $line->type,
            'contributes_to_coverage_planned' => $line->contributesToCoveragePlanned,
            'contributes_to_consumed' => $line->contributesToConsumed,
            'date' => $line->spendDate,
            'expense_cost_center' => [
                'id' => $line->costCenterId,
                'name' => $line->costCenterName,
            ],
            'plafond_cost_center' => [
                'id' => $line->plafondCostCenterId,
                'name' => $line->plafondCostCenterName,
            ],
            'amount' => self::measure($line->amount),
        ];
    }

    /** @return array<string, mixed> */
    public static function allocationLine(ProjectedEconomicLine $line, int $position, int $lockVersion): array
    {
        return [
            'id' => $line->rowId,
            'type' => $line->type,
            'position' => $position,
            'description' => $line->description,
            'notes' => $line->notes,
            'date' => $line->spendDate,
            'created_by' => [
                'id' => $line->createdByUserId,
                'name' => $line->createdByUserName,
            ],
            'lock_version' => $lockVersion,
            'amount' => self::measure($line->amount),
        ];
    }

    /** @return array<string, mixed> */
    public static function impact(PlafondImpact $impact, bool $includeBlockingRows = true): array
    {
        return [
            'plafond' => [
                'id' => $impact->current->plafondExpenseId,
                'title' => $impact->current->title,
            ],
            'currency' => $impact->current->currency,
            'basis' => $impact->current->basis,
            'current' => self::measures($impact->current),
            'proposed' => self::measures($impact->proposed),
            'requested' => $impact->requested,
            'shortage' => $impact->shortage,
            'can_confirm' => $impact->canConfirm,
            'blocking_rows' => $includeBlockingRows
                ? array_map(
                    static fn (ProjectedEconomicLine $line): array => self::coveredLine($line),
                    $impact->blockingRows,
                )
                : [],
        ];
    }
}
