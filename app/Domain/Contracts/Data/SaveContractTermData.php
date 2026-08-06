<?php

namespace App\Domain\Contracts\Data;

use App\Domain\Contracts\Enums\BillingCycle;

final readonly class SaveContractTermData
{
    public function __construct(
        public ?int $id,
        public string $localKey,
        public string $effectiveStart,
        public string $effectiveEnd,
        public BillingCycle $billingCycle,
        public ?string $quantity,
        public ?string $unitPrice,
        public string $enteredAmount,
        public bool $amountIncludesVat,
        public string $vatRate,
        public bool $autoRenew,
        public ?int $expectedLockVersion,
    ) {}
}
