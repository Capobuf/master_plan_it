<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'vat_number' => $this->resource->vat_number === null ? null : (string) $this->resource->vat_number,
            'email' => $this->resource->email === null ? null : (string) $this->resource->email,
            'phone' => $this->resource->phone === null ? null : (string) $this->resource->phone,
            'address' => $this->resource->address === null ? null : (string) $this->resource->address,
            'active' => (bool) $this->resource->active,
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
