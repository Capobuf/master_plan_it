<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Models\Tenant;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
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

    public function test_download_streams_exact_private_payload_and_headers(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('csv', 'Consuntivo.csv'), (string) str()->uuid());

        $response = app(AttachmentQuery::class)->download($media, Request::create('/download'));
        ob_start();
        $response->sendContent();
        $payload = (string) ob_get_clean();

        $this->assertSame("voce,importo\nDemo,10.00\n", $payload);
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Consuntivo.csv', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen($payload), $response->headers->get('Content-Length'));
    }

    public function test_parent_mismatch_and_foreign_tenant_lookup_are_not_found(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $media = app(UploadAttachment::class)->execute($actor, $context, $parents['expense'], $this->attachmentFile('pdf'), (string) str()->uuid());

        foreach ([$parents['project'], $this->supportedAttachmentParents(Tenant::factory()->create())['expense']] as $wrongParent) {
            try {
                app(AttachmentQuery::class)->findForParent($context, $wrongParent, (int) $media->getKey());
                $this->fail('Mismatched attachment lookup succeeded.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_missing_private_file_is_reported_without_deleting_metadata(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor, $context] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $media = app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        Storage::disk('attachments')->delete($media->getPathRelativeToRoot());

        try {
            app(AttachmentQuery::class)->download($media, Request::create('/download'));
            $this->fail('Missing file was streamed.');
        } catch (DomainException $exception) {
            $this->assertSame('ATTACHMENT_FILE_MISSING', $exception->getMessage());
        }
        $this->assertDatabaseHas('media', ['id' => $media->getKey()]);
    }
}
