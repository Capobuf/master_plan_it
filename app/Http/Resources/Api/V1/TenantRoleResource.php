<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read \Spatie\Permission\Models\Role $resource */
final class TenantRoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $abilities = $this->resource->relationLoaded('permissions')
            ? $this->resource->permissions
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->sort()
                ->values()
                ->all()
            : [];

        return [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'abilities' => $abilities,
        ];
    }
}
