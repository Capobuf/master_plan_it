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
        public ?string $vatRate,
        public bool $suppressed,
        public ?int $expenseId,
        public ?string $planningState,
        /** @var null|array{net:string,vat:string,gross:string} */
        public ?array $expectedDifference,
    ) {}
}
