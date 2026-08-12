<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Data\ExpenseDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read ExpenseDetail $resource */
final class ExpenseDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $expense = $this->resource;

        return [
            'id' => $expense->id,
            'planning_year_id' => $expense->planningYearId,
            'economic_year_label' => $expense->economicYearLabel,
            'cost_center_id' => $expense->costCenterId,
            'cost_center_name' => $expense->costCenterName,
            'cost_center' => [
                'id' => $expense->costCenterId,
                'name' => $expense->costCenterName,
            ],
            'kind' => $expense->kind,
            'title' => $expense->title,
            'notes' => $expense->notes,
            'project_id' => $expense->projectId,
            'project_title' => $expense->projectTitle,
            'project_current' => $expense->projectCurrent,
            'contract_id' => $expense->contractId,
            'contract_title' => $expense->contractTitle,
            'contract_current' => $expense->contractCurrent,
            'current_planning_row_id' => $expense->currentPlanningRowId,
            'lock_version' => $expense->lockVersion,
            'warnings' => $expense->budgetState === 'closed' ? ['BUDGET_CLOSED'] : [],
            'revision_activity' => $expense->revisionActivity,
            'budget_context' => [
                'state' => $expense->budgetState,
                'read_only' => $expense->budgetState === 'closed',
            ],
            'currency' => $expense->currency,
            'basis' => $expense->basis,
            'totals' => $expense->totals,
            'rows' => ExpenseRowResource::collection(collect($expense->rows))->resolve($request),
        ];
    }
}
