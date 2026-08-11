<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Economics\Data\EconomicScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read EconomicScope $resource */
final class ReportingScopeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $scope = $this->resource;

        return [
            'tenant_id' => $scope->tenantId,
            'planning_year_id' => $scope->planningYearId,
            'year' => $scope->yearLabel,
            'currency' => $scope->currency,
            'official_basis' => $scope->budgetBasis->value,
        ];
    }
}
