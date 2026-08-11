<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantSettingsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locked = $this->resource->approvalOperations()->exists();

        return [
            'tenant_id' => (int) $this->resource->getKey(),
            'name' => (string) $this->resource->name,
            'currency_code' => (string) $this->resource->currency_code,
            'timezone' => (string) $this->resource->timezone,
            'default_vat_rate' => (string) $this->resource->default_vat_rate,
            'budget_basis' => (string) $this->resource->getRawOriginal('budget_basis'),
            'budget_basis_locked' => $locked,
            'budget_basis_lock_reason' => $locked ? 'TENANT_BUDGET_BASIS_LOCKED' : null,
            'deletion_reason_required' => (bool) $this->resource->deletion_reason_required,
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
