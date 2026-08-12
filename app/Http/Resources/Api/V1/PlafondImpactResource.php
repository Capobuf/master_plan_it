<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Economics\Data\PlafondImpact;
use App\Domain\Plafonds\Data\PlafondProjectionSerializer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read PlafondImpact $resource */
final class PlafondImpactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return PlafondProjectionSerializer::impact($this->resource);
    }
}
