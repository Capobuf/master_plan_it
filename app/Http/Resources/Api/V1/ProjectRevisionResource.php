<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Revisions\Data\RevisionOperation;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            $batch = $this->resource['batch'];

            return [
                'revision' => $this->metadata($batch),
                'snapshot' => $this->resource['snapshot'],
                'current' => $this->resource['current'],
            ];
        }

        return $this->metadata($this->resource);
    }

    /** @return array<string, mixed> */
    private function metadata(RevisionBatch $batch): array
    {
        $operation = $batch->operation;
        $sourceItem = $batch->relationLoaded('items')
            ? $batch->items->first(fn (RevisionBatchItem $item): bool => $item->sequence === 1)
            : null;

        return [
            'id' => (int) $batch->getKey(),
            'operation' => $operation instanceof RevisionOperation ? $operation->value : (string) $batch->getRawOriginal('operation'),
            'actor' => $batch->actor?->name,
            'timestamp' => $batch->occurred_at?->toIso8601String(),
            'summary' => $batch->reason,
            'source_revision_id' => $sourceItem instanceof RevisionBatchItem ? (int) $sourceItem->version_id : null,
            'restored_from_revision_id' => $batch->restored_from_version_id === null ? null : (int) $batch->restored_from_version_id,
        ];
    }
}
