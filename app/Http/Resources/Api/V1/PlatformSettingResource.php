<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PlatformSettingResource extends JsonResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return [
            'audit_retention_months' => (int) $this->resource->audit_retention_months,
            'lock_version' => (int) $this->resource->lock_version,
        ];
    }
}
