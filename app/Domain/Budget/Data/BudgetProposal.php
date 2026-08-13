<?php

namespace App\Domain\Budget\Data;

use App\Domain\Economics\Data\EconomicMeasure;

final readonly class BudgetProposal
{
    /**
     * @param  list<ApprovalContributor>  $contributors
     * @param  list<ApprovalExclusion>  $exclusions
     */
    public function __construct(
        public int $tenantId,
        public int $planningYearId,
        public int $yearLabel,
        public string $currency,
        public string $basis,
        public BudgetCompositionEvidence $composition,
        public EconomicMeasure $total,
        public array $contributors,
        public array $exclusions,
    ) {}

    public function isEmpty(): bool
    {
        return $this->contributors === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'composition' => $this->composition->toArray(),
            'total' => [
                'net' => $this->total->net,
                'vat' => $this->total->vat,
                'gross' => $this->total->gross,
                'official' => $this->total->official,
            ],
            'contributors' => array_map(static fn (ApprovalContributor $item): array => $item->toArray(), $this->contributors),
            'exclusions' => array_map(static fn (ApprovalExclusion $item): array => $item->toArray(), $this->exclusions),
        ];
    }
}
