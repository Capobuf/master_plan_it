<?php

namespace App\Domain\Budget\Data;

final readonly class BudgetApprovalPreview
{
    public function __construct(
        public int $planningYearId,
        public int $yearLabel,
        public string $state,
        public int $lockVersion,
        public BudgetProposal $proposal,
        public bool $canApprove,
    ) {}
}
