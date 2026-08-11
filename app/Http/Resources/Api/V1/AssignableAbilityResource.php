<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{name: string, label: string} $resource */
final class AssignableAbilityResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'name' => (string) $this->resource['name'],
            'label' => (string) $this->resource['label'],
        ];
    }
}
