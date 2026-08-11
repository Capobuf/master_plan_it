<?php

namespace App\Domain\Attachments\Queries;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Media;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentQuery
{
    /** @return Collection<int, Media> */
    public function forParent(TenantContext $context, Model&HasMedia $parent, bool $lock = false): Collection
    {
        $query = $this->parentQuery($context, $parent)
            ->with('uploadedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function usageForTenant(TenantContext $context): string
    {
        return (string) (Media::query()
            ->where('tenant_id', $context->tenantId)
            ->sum('size') ?: '0');
    }

    public function findForParent(TenantContext $context, Model&HasMedia $parent, int $attachmentId, bool $lock = false): Media
    {
        $query = $this->parentQuery($context, $parent)->whereKey($attachmentId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function download(Media $media, Request $request): StreamedResponse
    {
        if ($media->disk !== 'attachments'
            || $media->collection_name !== 'attachments'
            || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            throw new DomainException('ATTACHMENT_FILE_MISSING');
        }

        return $media->toResponse($request);
    }

    /** @return Builder<Media> */
    private function parentQuery(TenantContext $context, Model&HasMedia $parent): Builder
    {
        return Media::query()
            ->where('tenant_id', $context->tenantId)
            ->where('model_type', $parent->getMorphClass())
            ->where('model_id', $parent->getKey())
            ->where('collection_name', 'attachments')
            ->where('disk', 'attachments');
    }
}
