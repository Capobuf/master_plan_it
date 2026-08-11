<?php

namespace App\Domain\Budget\Data;

final readonly class ApplyApprovalData
{
    /** @param list<ApprovalChangeData> $items */
    public function __construct(
        public int $budgetLockVersion,
        public string $effectiveDate,
        public ?string $reason,
        public array $items,
    ) {}
}
