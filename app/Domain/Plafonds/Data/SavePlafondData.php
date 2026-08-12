<?php

namespace App\Domain\Plafonds\Data;

final readonly class SavePlafondData
{
    public function __construct(
        public int $planningYearId,
        public int $costCenterId,
        public string $title,
        public ?string $notes,
        public AllocationAdjustmentData $initialAllocation,
    ) {}
}
