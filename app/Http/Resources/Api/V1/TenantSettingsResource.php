<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantSettingsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'currency_code' => (string) $this->resource->currency_code,
            'timezone' => (string) $this->resource->timezone,
            'default_vat_rate' => (string) $this->resource->default_vat_rate,
            'economic_basis' => (string) $this->resource->getRawOriginal('budget_basis'),
            'economic_basis_locked_at' => $this->resource->economic_basis_locked_at?->toISOString(),
            'deletion_reason_required' => (bool) $this->resource->deletion_reason_required,
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
