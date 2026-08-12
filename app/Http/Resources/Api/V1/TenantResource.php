<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'code' => (string) $this->resource->code,
            'currency_code' => (string) $this->resource->currency_code,
            'language_code' => (string) $this->resource->language_code,
            'timezone' => (string) $this->resource->timezone,
            'default_vat_rate' => (string) $this->resource->default_vat_rate,
            'economic_basis' => (string) $this->resource->getRawOriginal('budget_basis'),
            'state' => (string) $this->resource->getRawOriginal('state'),
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
