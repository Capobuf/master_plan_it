<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Budget\Data\BudgetApprovalPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BudgetApprovalPreview */
final class BudgetProposalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $proposal = $this->resource->proposal;

        return [
            'planning_year' => [
                'id' => $this->resource->planningYearId,
                'year_label' => $this->resource->yearLabel,
                'state' => $this->resource->state,
                'lock_version' => $this->resource->lockVersion,
            ],
            'currency' => $proposal->currency,
            'basis' => $proposal->basis,
            'effective_date_max' => $this->resource->effectiveDateMax,
            'surface_fingerprint' => $this->resource->surfaceFingerprint,
            'composition' => $proposal->composition->toArray(),
            'total' => [
                'net' => $proposal->total->net,
                'vat' => $proposal->total->vat,
                'gross' => $proposal->total->gross,
                'official' => $proposal->total->official,
            ],
            'contributors' => ApprovalContributorResource::collection($proposal->contributors),
            'exclusions' => ApprovalExclusionResource::collection($proposal->exclusions),
            'can_approve' => $this->resource->canApprove,
            'empty_composition' => $proposal->isEmpty(),
        ];
    }
}
