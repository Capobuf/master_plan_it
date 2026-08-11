<?php

namespace App\Domain\Expenses\Data;

final readonly class ExpenseDetail
{
    /**
     * @param list<array{
     *     id: int,
     *     position: int,
     *     vendor_id: ?int,
     *     vendor_name: ?string,
     *     type: string,
     *     is_current_planning: bool,
     *     description: string,
     *     quantity: ?string,
     *     unit_price: ?string,
     *     entered_amount: string,
     *     amount_includes_vat: bool,
     *     vat_rate: string,
     *     is_extra: bool,
     *     funded_plafond_expense_id: ?int,
     *     spend_date: ?string,
     *     external_reference: ?string,
     *     lock_version: int,
     *     is_system_managed: bool,
     *     generated: bool,
     *     contract_term_id: ?int,
     *     net_amount: string,
     *     vat_amount: string,
     *     gross_amount: string
     * }> $rows
     * @param  list<array{id:int,operation:string,actor:?string,timestamp:?string,summary:?string}>  $revisionActivity
     */
    public function __construct(
        public int $id,
        public int $planningYearId,
        public int $planningYearLabel,
        public int $costCenterId,
        public string $costCenterName,
        public string $kind,
        public string $title,
        public ?string $notes,
        public ?int $projectId,
        public ?string $projectTitle,
        public ?int $contractId,
        public ?string $contractTitle,
        public string $budgetState,
        public string $state,
        public ?string $closureOutcome,
        public ?string $approvedAmount,
        public ?string $approvedBasis,
        public ?int $currentPlanningRowId,
        public ?int $movedFromExpenseId,
        public ?int $creditForExpenseId,
        public ?string $plannedAmount,
        public string $actualAmount,
        public ?string $residualAmount,
        public ?string $varianceAmount,
        public int $lockVersion,
        public array $rows,
        public string $netTotal,
        public string $vatTotal,
        public string $grossTotal,
        public array $revisionActivity,
    ) {}
}
