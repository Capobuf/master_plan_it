<?php

namespace App\Domain\Budget\Data;

use App\Domain\Economics\Data\EconomicMeasure;

final readonly class ApprovalExclusion
{
    public function __construct(
        public string $sourceIdentity,
        public string $reason,
        public ?int $expenseId,
        public ?string $expenseTitle,
        public ?int $rowId,
        public ?string $rowType,
        public ?string $rowDescription,
        public EconomicMeasure $amount,
        public string $detail,
        public bool $drillDownAuthorized,
        public ?string $drillDownHref,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_identity' => $this->sourceIdentity,
            'reason' => $this->reason,
            'expense' => $this->expenseId === null ? null : ['id' => $this->expenseId, 'title' => $this->expenseTitle],
            'row' => $this->rowId === null ? null : [
                'id' => $this->rowId,
                'type' => $this->rowType,
                'description' => $this->rowDescription,
            ],
            'amount' => [
                'net' => $this->amount->net,
                'vat' => $this->amount->vat,
                'gross' => $this->amount->gross,
                'official' => $this->amount->official,
            ],
            'detail' => $this->detail,
            'drill_down' => [
                'href' => $this->drillDownHref,
                'authorized' => $this->drillDownAuthorized,
            ],
        ];
    }
}
