<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Data\ExpenseRegisterRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ExpenseRegisterRow $resource */
final class ExpenseRegisterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id' => $row->id,
            'planning_year_id' => $row->planningYearId,
            'economic_year_label' => $row->economicYearLabel,
            'cost_center_id' => $row->costCenterId,
            'cost_center_name' => $row->costCenterName,
            'kind' => $row->kind,
            'title' => $row->title,
            'project_id' => $row->projectId,
            'project_title' => $row->projectTitle,
            'project_current' => $row->projectCurrent,
            'contract_id' => $row->contractId,
            'contract_title' => $row->contractTitle,
            'contract_current' => $row->contractCurrent,
            'current_planning_row_id' => $row->currentPlanningRowId,
            'vendor_count' => $row->vendorCount,
            'vendor_summary' => $row->vendorSummary,
            'row_count' => $row->rowCount,
            'lock_version' => $row->lockVersion,
            'currency' => $row->currency,
            'basis' => $row->basis,
            'totals' => $row->totals,
        ];
    }
}
