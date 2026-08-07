<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Economics\Data\EconomicDataset;
use App\Domain\Economics\Data\EconomicSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{dataset: EconomicDataset, calculated: array{summary: EconomicSummary, monthly: array<string, string>, byType: array<string, string>, byCostCenter: array<string, string>}, cost_center_id: int|null} $resource */
final class ReportingDatasetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $payload = $this->resource;
        $dataset = $payload['dataset'];
        $calculated = $payload['calculated'];

        return [
            'scope' => ReportingScopeResource::make($dataset->scope)->resolve($request),
            'summary' => ReportingSummaryResource::make($calculated['summary'])->resolve($request),
            'monthly' => $calculated['monthly'],
            'by_type' => $calculated['byType'],
            'by_cost_center' => $calculated['byCostCenter'],
            'cost_center_id' => $payload['cost_center_id'] ?? null,
            'has_economic_data' => $dataset->lines !== [],
        ];
    }
}
