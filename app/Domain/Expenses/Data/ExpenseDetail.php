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
        public array $rows,
        public string $netTotal,
        public string $vatTotal,
        public string $grossTotal,
    ) {}
}
