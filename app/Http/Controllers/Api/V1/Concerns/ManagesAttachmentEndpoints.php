<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Domain\Attachments\Actions\DeleteAttachment;
use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Attachments\Services\AttachmentAuthorization;
use App\Http\Resources\Api\V1\AttachmentResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesAttachmentEndpoints
{
    private function attachmentIndex(Request $request, Model&HasMedia $parent, AttachmentQuery $query, AttachmentAuthorization $authorization): AnonymousResourceCollection
    {
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $authorization->authorizeView($actor, $context, $parent);

        return AttachmentResource::collection($query->forParent($context, $parent))->additional([
            'meta' => [
                'used_bytes' => $query->usageForTenant($context),
                'quota_bytes' => (string) $context->tenant->attachment_quota_bytes,
            ],
            'abilities' => $authorization->abilities($actor, $context, $parent),
        ]);
    }

    private function attachmentStore(Request $request, Model&HasMedia $parent, UploadAttachment $action, AttachmentQuery $query, AttachmentAuthorization $authorization): JsonResponse
    {
        $this->rejectAttachmentFields($request, ['file']);
        $validated = $request->validate(['file' => ['required', 'file']]);
        $actor = $this->actor($request);
        $context = $this->tenantContext($request);
        $media = $action->execute($actor, $context, $parent, $validated['file'], $this->correlationId($request));

        return AttachmentResource::make($media)->additional([
            'meta' => [
                'used_bytes' => $query->usageForTenant($context),
                'quota_bytes' => (string) $context->tenant->attachment_quota_bytes,
            ],
            'abilities' => $authorization->abilities($actor, $context, $parent),
        ])->response()->setStatusCode(201);
    }

    private function attachmentDownload(Request $request, Model&HasMedia $parent, int $attachment, AttachmentQuery $query, AttachmentAuthorization $authorization): StreamedResponse
    {
        $context = $this->tenantContext($request);
        $authorization->authorizeView($this->actor($request), $context, $parent);

        return $query->download($query->findForParent($context, $parent, $attachment), $request);
    }

    private function attachmentDestroy(Request $request, Model&HasMedia $parent, int $attachment, DeleteAttachment $action): Response
    {
        $this->rejectAttachmentFields($request, []);
        $action->execute($this->actor($request), $this->tenantContext($request), $parent, $attachment, $this->correlationId($request));

        return response()->noContent();
    }

    /** @param list<string> $allowed */
    private function rejectAttachmentFields(Request $request, array $allowed): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected fields: '.implode(', ', $unexpected).'.'],
            ]);
        }
    }
}
