<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\UploadAttachment;
use App\Models\AuditEvent;
use App\Models\Media;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentUploadRollbackTest extends TestCase
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

    public function test_audit_failure_rolls_back_metadata_and_compensates_private_file(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $correlationId = (string) str()->uuid();
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $dispatcher->listen($eventName, static function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(UploadAttachment::class));
            $action = app(UploadAttachment::class);
            $action->execute($actor, $context, $expense, $this->attachmentFile('pdf'), $correlationId);
        } finally {
            $dispatcher->forget($eventName);
            foreach ($listeners as $listener) {
                $dispatcher->listen($eventName, $listener);
            }
            $this->assertDatabaseCount('media', 0);
            $this->assertDatabaseMissing('media', ['tenant_id' => $tenant->getKey()]);
            $this->assertDatabaseCount('audit_events', 0);
            $this->assertSame([], Storage::disk('attachments')->allFiles());
        }
    }

    public function test_filesystem_failure_is_diagnostic_and_leaves_no_metadata_or_audit(): void
    {
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        config()->set('filesystems.disks.attachments.root', '/dev/null/attachments');
        Storage::forgetDisk('attachments');

        try {
            app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
            $this->fail('Filesystem failure was reported as a successful upload.');
        } catch (\DomainException $exception) {
            $this->assertSame('ATTACHMENT_STORAGE_FAILURE', $exception->getMessage());
        }

        $this->assertSame(0, Media::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(0, AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'attachment.uploaded')->count());
    }
}
