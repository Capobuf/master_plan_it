<?php

namespace App\Domain\Attachments\Actions;

use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Attachments\Services\AttachmentAuthorization;
use App\Domain\Attachments\Services\AttachmentFileValidator;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use League\Flysystem\FilesystemException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\DiskCannotBeAccessed;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Throwable;

final class UploadAttachment
{
    public function __construct(
        private readonly AttachmentAuthorization $authorization,
        private readonly AttachmentFileValidator $validator,
        private readonly AttachmentQuery $query,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(
        User $actor,
        TenantContext $context,
        Model&HasMedia $parent,
        UploadedFile $file,
        string $correlationId,
    ): Media {
        $this->authorization->authorizeUpload($actor, $context, $parent);
        $validated = $this->validator->validate($file);
        $created = null;

        try {
            return DB::transaction(function () use ($actor, $context, $correlationId, $file, $parent, &$created, $validated): Media {
                $tenant = Tenant::query()->lockForUpdate()->find($context->tenantId);
                if (! $tenant instanceof Tenant) {
                    throw new DomainException('TENANT_CONTEXT_REQUIRED');
                }

                $usedBytes = $this->query->usageForTenant($context);
                $quotaBytes = (string) $tenant->attachment_quota_bytes;
                if (bccomp(bcadd($usedBytes, (string) $validated['size'], 0), $quotaBytes, 0) === 1) {
                    throw new DomainException('ATTACHMENT_QUOTA_EXCEEDED');
                }

                $created = $parent->addMedia($file)
                    ->usingName($validated['original_name'])
                    ->usingFileName($validated['storage_name'])
                    ->withProperties([
                        'tenant_id' => $context->tenantId,
                        'uploaded_by_user_id' => $actor->getKey(),
                    ])
                    ->toMediaCollection('attachments', 'attachments');

                if (! $created instanceof Media) {
                    throw new DomainException('ATTACHMENT_STORAGE_FAILURE');
                }

                $this->auditRecorder->record(
                    'attachment.uploaded',
                    $correlationId,
                    new AuditProperties($this->auditProperties($created, $parent)),
                    $actor,
                    $context->tenantId,
                    $parent,
                );

                return $created->load('uploadedBy:id,name');
            });
        } catch (Throwable $exception) {
            if ($created instanceof Media) {
                try {
                    app(Filesystem::class)->removeAllFiles($created);
                } catch (Throwable) {
                    // Preserve the original diagnostic; the throwing disk has already reported the cleanup failure.
                }
            }

            if ($exception instanceof DiskCannotBeAccessed || $exception instanceof FilesystemException) {
                throw new DomainException('ATTACHMENT_STORAGE_FAILURE', previous: $exception);
            }

            throw $exception;
        }
    }

    /** @return array{filename: string, size: int, mime: string, parent_type: string, parent_id: int} */
    private function auditProperties(Media $media, Model $parent): array
    {
        return [
            'filename' => (string) $media->name,
            'size' => (int) $media->size,
            'mime' => (string) $media->mime_type,
            'parent_type' => class_basename($parent),
            'parent_id' => (int) $parent->getKey(),
        ];
    }
}
