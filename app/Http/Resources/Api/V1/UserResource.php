<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'email' => (string) $this->resource->email,
            'active' => (bool) $this->resource->is_active,
            'tenant_id' => $this->resource->tenant_id === null ? null : (int) $this->resource->tenant_id,
        ];
    }
}
