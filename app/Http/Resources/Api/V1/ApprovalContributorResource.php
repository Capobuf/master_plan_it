<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Budget\Data\ApprovalContributor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalContributor */
final class ApprovalContributorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
