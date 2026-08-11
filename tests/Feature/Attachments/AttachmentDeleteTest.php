<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\DeleteAttachment;
use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Models\AuditEvent;
use App\Models\Media;
use App\Models\RevisionBatch;
use App\Models\Version;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentDeleteTest extends TestCase
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

    public function test_delete_hard_deletes_metadata_payload_frees_quota_and_writes_minimal_audit(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf', 'Fattura.pdf'), (string) str()->uuid());
        $path = $media->getPathRelativeToRoot();
        $versionCount = Version::withTrashed()->count();
        $batchCount = RevisionBatch::query()->count();

        app(DeleteAttachment::class)->execute($actor, $context, $expense, (int) $media->getKey(), (string) str()->uuid());

        $this->assertDatabaseMissing('media', ['id' => $media->getKey()]);
        Storage::disk('attachments')->assertMissing($path);
        $this->assertSame('0', app(AttachmentQuery::class)->usageForTenant($context));
        $audit = AuditEvent::query()->where('event_type', 'attachment.deleted')->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->assertSame('Fattura.pdf', $audit->properties['filename']);
        $this->assertArrayNotHasKey('path', $audit->properties);
        $this->assertSame($versionCount, Version::withTrashed()->count());
        $this->assertSame($batchCount, RevisionBatch::query()->count());
    }

    public function test_delete_requires_permission_and_exact_parent(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $media = app(UploadAttachment::class)->execute($actor, $context, $parents['expense'], $this->attachmentFile('pdf'), (string) str()->uuid());

        $this->revokeAttachmentAbility($actor, $tenant, 'attachment.delete');
        try {
            app(DeleteAttachment::class)->execute($actor, $context, $parents['expense'], (int) $media->getKey(), (string) str()->uuid());
            $this->fail('Delete without ability succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('PERMISSION_DENIED', $exception->getMessage());
        }
        $this->assertDatabaseHas('media', ['id' => $media->getKey()]);

        // Restore the permission so the exact-parent check is the only failing condition.
        $this->seedApiPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $role = $actor->roles()->where('roles.tenant_id', $tenant->getKey())->firstOrFail();
        $role->givePermissionTo('attachment.delete');
        try {
            app(DeleteAttachment::class)->execute($actor, $context, $parents['project'], (int) $media->getKey(), (string) str()->uuid());
            $this->fail('Delete through mismatched parent succeeded.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, Media::query()->whereKey($media->getKey())->count());
    }

    public function test_delete_audit_failure_preserves_metadata_and_payload(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
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
            self::assertTrue(class_exists(DeleteAttachment::class));
            $action = app(DeleteAttachment::class);
            $action->execute($actor, $context, $expense, (int) $media->getKey(), $correlationId);
        } finally {
            $dispatcher->forget($eventName);
            foreach ($listeners as $listener) {
                $dispatcher->listen($eventName, $listener);
            }
            $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
            $this->assertDatabaseCount('audit_events', $auditCount);
            Storage::disk('attachments')->assertExists($media->getPathRelativeToRoot());
        }
    }

    public function test_delete_filesystem_failure_rolls_back_metadata_and_stays_diagnostic(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        $path = $media->getPathRelativeToRoot();

        $disk = Mockery::mock(FilesystemContract::class);
        $disk->shouldReceive('exists')->once()->andReturnTrue();
        $disk->shouldReceive('deleteDirectory')->once()->andReturnFalse();
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('disk')->once()->with('attachments')->andReturn($disk);
        $this->app->instance(Factory::class, $factory);

        try {
            app(DeleteAttachment::class)->execute($actor, $context, $expense, (int) $media->getKey(), (string) str()->uuid());
            $this->fail('Filesystem delete failure was reported as successful.');
        } catch (\DomainException $exception) {
            $this->assertSame('ATTACHMENT_STORAGE_FAILURE', $exception->getMessage());
        }

        $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
        Storage::disk('attachments')->assertExists($path);
    }
}
