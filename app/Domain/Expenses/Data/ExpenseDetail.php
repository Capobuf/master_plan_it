<?php

namespace App\Domain\Expenses\Data;

final readonly class ExpenseDetail
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $revisionActivity
     * @param  array{current_planning: array<string, string>, actual: array<string, string>}  $totals
     */
    public function __construct(
        public int $id,
        public int $planningYearId,
        public int $economicYearLabel,
        public int $costCenterId,
        public string $costCenterName,
        public string $kind,
        public string $title,
        public ?string $notes,
        public ?int $projectId,
        public ?string $projectTitle,
        public bool $projectCurrent,
        public ?int $contractId,
        public ?string $contractTitle,
        public bool $contractCurrent,
        public string $budgetState,
        public ?int $currentPlanningRowId,
        public int $lockVersion,
        public string $currency,
        public string $basis,
        public array $totals,
        public array $rows,
        public array $revisionActivity,
    ) {}
}
