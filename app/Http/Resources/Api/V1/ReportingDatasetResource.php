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

        if ($dataset === null || $calculated === null) {
            return [
                'scope' => null,
                'summary' => null,
                'monthly' => [],
                'by_type' => [],
                'by_cost_center' => [],
                'cost_center_id' => null,
                'has_economic_data' => false,
                'year_options' => $payload['year_options'] ?? [],
                'selected_year_id' => null,
                'ancillary' => $payload['ancillary'] ?? [],
            ];
        }

        $result = [
            'scope' => ReportingScopeResource::make($dataset->scope)->resolve($request),
            'summary' => ReportingSummaryResource::make($calculated['summary'])->resolve($request),
            'monthly' => $calculated['monthly'],
            'by_type' => $calculated['byType'],
            'by_cost_center' => $calculated['byCostCenter'],
            'cost_center_id' => $payload['cost_center_id'] ?? null,
            'has_economic_data' => $dataset->lines !== [],
        ];

        if (($payload['dashboard'] ?? false) === true) {
            $result['year_options'] = $payload['year_options'] ?? [];
            $result['selected_year_id'] = $payload['selected_year_id'] ?? null;
            $result['ancillary'] = $payload['ancillary'] ?? [];
        }

        return $result;
    }
}
