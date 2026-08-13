<?php

namespace App\Domain\Budget\Data;

final readonly class BudgetSourceAccess
{
    public function __construct(
        public bool $expense,
        public bool $planningYear,
        public bool $costCenter,
        public bool $vendor,
        public bool $project,
        public bool $contract,
        public bool $expenseUpdate,
    ) {}
}
