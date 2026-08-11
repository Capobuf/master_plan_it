<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Models\AuditEvent;
use App\Models\Media;
use App\Models\RevisionBatch;
use App\Models\Version;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use CreatesAttachmentFiles;
    use DatabaseTransactions;
    use InteractsWithAttachments;

    protected function tearDown(): void
    {
        $this->cleanupAttachmentFiles();
        $this->resetAttachmentPermissionScope();
        parent::tearDown();
    }

    public function test_upload_persists_private_metadata_uploader_and_minimal_audit_for_every_supported_parent(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $versionCount = Version::withTrashed()->count();
        $batchCount = RevisionBatch::query()->count();

        foreach (array_values($parents) as $index => $parent) {
            $media = app(UploadAttachment::class)->execute(
                $actor,
                $context,
                $parent,
                $this->attachmentFile('pdf', 'Documento '.($index + 1).'.pdf'),
                (string) str()->uuid(),
            );

            $this->assertSame($tenant->getKey(), $media->tenant_id);
            $this->assertSame($actor->getKey(), $media->uploaded_by_user_id);
            $this->assertSame($parent->getMorphClass(), $media->model_type);
            $this->assertSame($parent->getKey(), $media->model_id);
            $this->assertSame('attachments', $media->disk);
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/', $media->file_name);
            Storage::disk('attachments')->assertExists($media->getPathRelativeToRoot());
            $this->assertSame([$media->getKey()], app(AttachmentQuery::class)->forParent($context, $parent)->pluck('id')->all());
        }

        $this->assertSame(4, Media::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(4, AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'attachment.uploaded')->count());
        $audit = AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'attachment.uploaded')->firstOrFail();
        $this->assertArrayNotHasKey('payload', $audit->properties);
        $this->assertArrayNotHasKey('path', $audit->properties);
        $this->assertSame($versionCount, Version::withTrashed()->count());
        $this->assertSame($batchCount, RevisionBatch::query()->count());
    }

    public function test_uploading_the_same_bytes_twice_creates_two_media_and_counts_both(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];

        $first = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('csv'), (string) str()->uuid());
        $second = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('csv'), (string) str()->uuid());

        $this->assertNotSame($first->getKey(), $second->getKey());
        $this->assertNotSame($first->file_name, $second->file_name);
        $this->assertSame((string) ($first->size + $second->size), app(AttachmentQuery::class)->usageForTenant($context));
    }
}
