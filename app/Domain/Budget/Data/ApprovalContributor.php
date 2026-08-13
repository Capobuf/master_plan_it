<?php

namespace App\Domain\Budget\Data;

use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Economics\Data\EconomicMeasure;

final readonly class ApprovalContributor
{
    public function __construct(
        public string $sourceIdentity,
        public ApprovalContributorKind $kind,
        public int $sourceLockVersion,
        public int $expenseId,
        public string $expenseTitle,
        public string $expenseKind,
        public ?int $rowId,
        public ?string $rowType,
        public ?string $rowDescription,
        public int $costCenterId,
        public string $costCenterName,
        public ?int $vendorId,
        public ?string $vendorName,
        public ?int $projectId,
        public ?string $projectTitle,
        public ?int $contractId,
        public ?string $contractTitle,
        public EconomicMeasure $amount,
        public bool $drillDownAuthorized,
        public ?string $drillDownHref,
    ) {}

    /** @return array<string, mixed> */
    public function canonicalData(): array
    {
        return [
            'amount' => [
                'gross' => $this->amount->gross,
                'net' => $this->amount->net,
                'official' => $this->amount->official,
                'vat' => $this->amount->vat,
            ],
            'component_kind' => $this->kind->value,
            'contract_id' => $this->contractId,
            'contract_title' => $this->contractTitle,
            'cost_center_id' => $this->costCenterId,
            'cost_center_name' => $this->costCenterName,
            'expense_id' => $this->expenseId,
            'expense_kind' => $this->expenseKind,
            'expense_title' => $this->expenseTitle,
            'project_id' => $this->projectId,
            'project_title' => $this->projectTitle,
            'row_description' => $this->rowDescription,
            'row_id' => $this->rowId,
            'row_type' => $this->rowType,
            'source_identity' => $this->sourceIdentity,
            'source_lock_version' => $this->sourceLockVersion,
            'vendor_id' => $this->vendorId,
            'vendor_name' => $this->vendorName,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $plafond = $this->kind === ApprovalContributorKind::PlafondAllocation
            ? ['id' => $this->expenseId, 'title' => $this->expenseTitle]
            : null;

        return [
            'source_identity' => $this->sourceIdentity,
            'kind' => $this->kind->value,
            'expense' => ['id' => $this->expenseId, 'title' => $this->expenseTitle],
            'row' => $this->rowId === null ? null : [
                'id' => $this->rowId,
                'type' => $this->rowType,
                'description' => $this->rowDescription,
            ],
            'plafond' => $plafond,
            'dimensions' => [
                'cost_center' => ['id' => $this->costCenterId, 'name' => $this->costCenterName],
                'vendor' => $this->vendorId === null ? null : ['id' => $this->vendorId, 'name' => $this->vendorName],
                'project' => $this->projectId === null ? null : ['id' => $this->projectId, 'title' => $this->projectTitle],
                'contract' => $this->contractId === null ? null : ['id' => $this->contractId, 'title' => $this->contractTitle],
            ],
            'amount' => [
                'net' => $this->amount->net,
                'vat' => $this->amount->vat,
                'gross' => $this->amount->gross,
                'official' => $this->amount->official,
            ],
            'source_lock_version' => $this->sourceLockVersion,
            'drill_down' => [
                'href' => $this->drillDownHref,
                'authorized' => $this->drillDownAuthorized,
            ],
        ];
    }
}
