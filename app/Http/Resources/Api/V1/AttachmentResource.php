<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Media $media */
        $media = $this->resource;
        $uploader = $media->getRelation('uploadedBy');

        return [
            'id' => (int) $media->getKey(),
            'name' => (string) $media->name,
            'mime_type' => (string) $media->mime_type,
            'extension' => strtolower((string) pathinfo($media->name, PATHINFO_EXTENSION)),
            'size' => (int) $media->size,
            'uploaded_at' => $media->created_at?->utc()->toIso8601String(),
            'uploaded_by' => [
                'name' => $uploader instanceof User ? $uploader->name : 'Utente non più disponibile',
            ],
        ];
    }
}
