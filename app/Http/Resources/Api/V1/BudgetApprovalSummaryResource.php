<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Models\BudgetApproval;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BudgetApproval */
final class BudgetApprovalSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $year = $this->resource->planningYear;
        $tenant = $this->resource->tenant;
        if (! $year instanceof PlanningYear || ! $tenant instanceof Tenant) {
            throw new \LogicException('Approval summary requires PlanningYear and Tenant relations.');
        }
        $status = $this->resource->getAttribute('status');
        $effectiveDate = $this->resource->getAttribute('effective_date');
        $recordedAt = $this->resource->getAttribute('recorded_at');
        $state = $year->getAttribute('budget_state');
        $basis = $tenant->getAttribute('budget_basis');
        $baseLockedAt = $tenant->getAttribute('economic_basis_locked_at');
        if (! $status instanceof BudgetApprovalStatus
            || ! $effectiveDate instanceof CarbonInterface
            || ! $recordedAt instanceof CarbonInterface
            || ! $state instanceof BudgetState
            || ! $basis instanceof BudgetBasis
            || ($baseLockedAt !== null && ! $baseLockedAt instanceof CarbonInterface)) {
            throw new \LogicException('Approval summary contains invalid persisted values.');
        }

        return [
            'approval' => [
                'id' => (int) $this->resource->getKey(),
                'status' => $status->value,
                'planning_year_id' => (int) $this->resource->planning_year_id,
                'currency' => (string) $this->resource->currency_code,
                'basis' => (string) $this->resource->budget_basis,
                'total' => [
                    'net' => (string) $this->resource->total_net_amount,
                    'vat' => (string) $this->resource->total_vat_amount,
                    'gross' => (string) $this->resource->total_gross_amount,
                    'official' => (string) $this->resource->total_official_amount,
                ],
                'effective_date' => $effectiveDate->toDateString(),
                'recorded_at' => $recordedAt->utc()->toISOString(),
                'approved_by' => [
                    'id' => (int) $this->resource->approved_by_user_id,
                    'name' => (string) $this->resource->approved_by_name,
                ],
                'note' => $this->resource->approval_note,
            ],
            'budget' => [
                'planning_year_id' => (int) $year->getKey(),
                'state' => $state->value,
                'lock_version' => (int) $year->lock_version,
            ],
            'economic_base' => [
                'basis' => $basis->value,
                'locked_at' => $baseLockedAt?->utc()->toISOString(),
            ],
        ];
    }
}
