<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuditEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(), 'tenant_id' => $this->resource->tenant_id === null ? null : (int) $this->resource->tenant_id,
            'actor_label' => $this->resource->actor_label, 'event_type' => (string) $this->resource->event_type,
            'subject_type' => $this->resource->subject_type, 'subject_id' => $this->resource->subject_id,
            'correlation_id' => (string) $this->resource->correlation_id, 'properties' => $this->resource->properties ?? [],
            'occurred_at' => $this->resource->occurred_at?->toISOString(),
        ];
    }
}
