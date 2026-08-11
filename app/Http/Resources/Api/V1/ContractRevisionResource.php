<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Revision metadata only; Version contents and persistence tombstones stay private. */
final class ContractRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource['id'],
            'operation' => (string) $this->resource['operation'],
            'actor' => $this->resource['actor'],
            'timestamp' => $this->resource['timestamp'],
            'summary' => $this->resource['summary'],
            'changed_count' => (int) $this->resource['changed_count'],
            'changed_fields' => $this->resource['changed_fields'],
            'can_compare' => (bool) $this->resource['can_compare'],
            'can_restore' => (bool) $this->resource['can_restore'],
        ];
    }
}
