<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A whitelist of business revision metadata; persistence payloads are never exposed. */
final class RevisionResource extends JsonResource
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
            'changed_fields' => $this->resource['changed_fields'] ?? [],
            'can_compare' => (bool) ($this->resource['can_compare'] ?? false),
            'can_restore' => (bool) $this->resource['can_restore'],
        ];
    }
}
