<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CostCenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'parent_id' => $this->resource->parent_id === null ? null : (int) $this->resource->parent_id,
            'active' => (bool) $this->resource->active,
            'lock_version' => (int) $this->resource->lock_version,
        ];

        if (isset($this->resource->depth)) {
            $data['depth'] = (int) $this->resource->depth;
        }

        if ($this->resource->relationLoaded('children')) {
            $data['children'] = self::collection($this->resource->children)
                ->resolve($request);
        }

        return $data;
    }
}
