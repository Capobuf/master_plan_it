<?php

namespace App\Domain\Budget\Data;

final readonly class ApprovalChangeData
{
    public function __construct(
        public int $expenseId,
        public int $expectedLockVersion,
        public string $approvedAmount,
    ) {}
}
