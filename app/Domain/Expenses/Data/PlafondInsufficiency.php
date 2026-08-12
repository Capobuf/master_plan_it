<?php

namespace App\Domain\Expenses\Data;

use App\Domain\Economics\Data\EconomicMeasure;
use App\Domain\Economics\Data\PlafondEconomicProjection;
use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Economics\Data\ProjectedEconomicLine;

final readonly class PlafondInsufficiency
{
    public function __construct(
        public int $plafondExpenseId,
        public string $currency,
        public string $basis,
        public string $allocated,
        public string $available,
        public string $required,
        public string $shortage,
        public string $field,
        public PlafondImpact $impact,
    ) {}

    /** @return array<string, mixed> */
    public function details(): array
    {
        $details = [
            'plafond_expense_id' => $this->plafondExpenseId,
            'currency' => $this->currency,
            'basis' => $this->basis,
            'allocated' => $this->allocated,
            'available' => $this->available,
            'required' => $this->required,
            'shortage' => $this->shortage,
            'impact' => [
                'current' => $this->measures($this->impact->current),
                'proposed' => $this->measures($this->impact->proposed),
                'blocking_rows' => array_map(
                    fn (ProjectedEconomicLine $line): array => [
                        'expense_id' => $line->expenseId,
                        'expense_title' => $line->expenseTitle,
                        'row_id' => $line->rowId,
                        'description' => $line->description,
                        'expense_cost_center' => [
                            'id' => $line->costCenterId,
                            'name' => $line->costCenterName,
                        ],
                        'plafond_cost_center' => [
                            'id' => $line->plafondCostCenterId,
                            'name' => $line->plafondCostCenterName,
                        ],
                        'amount' => $this->measure($line->amount),
                    ],
                    $this->impact->blockingRows,
                ),
            ],
        ];

        return $details;
    }

    /** @return array<string, array{net: string, vat: string, gross: string, official: string}> */
    private function measures(PlafondEconomicProjection $projection): array
    {
        return [
            'allocation' => $this->measure($projection->allocation),
            'coverage_planned' => $this->measure($projection->coveragePlanned),
            'consumed' => $this->measure($projection->consumed),
            'available' => $this->measure($projection->available),
        ];
    }

    /** @return array{net: string, vat: string, gross: string, official: string} */
    private function measure(EconomicMeasure $measure): array
    {
        return [
            'net' => $measure->net,
            'vat' => $measure->vat,
            'gross' => $measure->gross,
            'official' => $measure->official,
        ];
    }
}
