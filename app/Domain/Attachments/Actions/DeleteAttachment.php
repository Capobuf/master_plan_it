<?php

namespace App\Domain\Attachments\Actions;

use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Attachments\Services\AttachmentAuthorization;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;

final class DeleteAttachment
{
    public function __construct(
        private readonly AttachmentAuthorization $authorization,
        private readonly AttachmentQuery $query,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        Model&HasMedia $parent,
        int $attachmentId,
        string $correlationId,
    ): void {
        $this->authorization->authorizeDelete($actor, $context, $parent);

        DB::transaction(function () use ($actor, $attachmentId, $context, $correlationId, $parent): void {
            Tenant::query()->whereKey($context->tenantId)->lockForUpdate()->firstOrFail();
            $media = $this->query->findForParent($context, $parent, $attachmentId, true);
            $this->recordDeleted($media, $parent, $actor, $context, $correlationId);
            $media->delete();
        });
    }

    public function recordDeleted(Media $media, Model $parent, User $actor, TenantContext $context, string $correlationId): void
    {
        $this->auditRecorder->record(
            'attachment.deleted',
            $correlationId,
            new AuditProperties([
                'filename' => (string) $media->name,
                'size' => (int) $media->size,
                'mime' => (string) $media->mime_type,
                'parent_type' => class_basename($parent),
                'parent_id' => (int) $parent->getKey(),
            ]),
            $actor,
            $context->tenantId,
            $parent,
        );
    }
}
