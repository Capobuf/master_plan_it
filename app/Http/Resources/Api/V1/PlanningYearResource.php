<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\MasterData\Data\CreatePlanningYearData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read \App\Models\PlanningYear $resource */
final class PlanningYearResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = new CreatePlanningYearData((int) $this->resource->year_label);

        return [
            'id' => (int) $this->resource->getKey(),
            'year_label' => (int) $this->resource->year_label,
            'start_date' => $data->startDate(),
            'end_date' => $data->endDate(),
            'active' => (bool) $this->resource->active,
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
