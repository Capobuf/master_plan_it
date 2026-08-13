<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Budget\Data\ApprovalExclusion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalExclusion */
final class ApprovalExclusionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
