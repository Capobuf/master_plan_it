<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContextResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $tenant = $this->resource['tenant'];

        return [
            'user' => UserResource::make($user),
            'platformAdministrator' => (bool) $this->resource['platformAdministrator'],
            'tenant' => $tenant === null ? null : TenantResource::make($tenant),
            'abilities' => array_values($this->resource['abilities']),
        ];
    }
}
