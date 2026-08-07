<?php

namespace App\Domain\Expenses\Data;

final readonly class ExpenseDetail
{
    /**
     * @param list<array{
     *     id: int,
     *     position: int,
     *     vendor_id: ?int,
     *     type: string,
     *     confirmation_state: ?string,
     *     description: string,
     *     quantity: ?string,
     *     unit_price: ?string,
     *     entered_amount: string,
     *     amount_includes_vat: bool,
     *     vat_rate: string,
     *     is_extra: bool,
     *     funded_plafond_expense_id: ?int,
     *     spend_date: ?string,
     *     period_start: ?string,
     *     period_end: ?string,
     *     distribution: ?string,
     *     external_reference: ?string,
     *     lock_version: int,
     *     is_system_managed: bool,
     *     generated: bool,
     *     contract_term_id: ?int,
     *     net_amount: string,
     *     vat_amount: string,
     *     gross_amount: string
     * }> $rows
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
        public ?int $contractId,
        public int $lockVersion,
        public array $rows,
        public string $netTotal,
        public string $vatTotal,
        public string $grossTotal,
    ) {}
}
