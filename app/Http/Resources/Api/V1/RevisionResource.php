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
            'operation' => (string) $this->resource['operation'],
            'actor' => $this->resource['actor'],
            'timestamp' => $this->resource['timestamp'],
            'reason' => $this->resource['reason'],
            'source_revision_id' => $this->resource['source_revision_id'],
            'restored_from_revision_id' => $this->resource['restored_from_revision_id'],
        ];
    }
}
