<?php

namespace App\Domain\Contracts\Data;

final readonly class ExpectedContractOccurrence
{
    public function __construct(
        public int $contractId,
        public int $termId,
        public int $planningYear,
        public string $occurrenceDate,
        public string $sourceKey,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
        public bool $suppressed,
        public ?int $expenseId,
        public ?string $generationState,
    ) {}
}
