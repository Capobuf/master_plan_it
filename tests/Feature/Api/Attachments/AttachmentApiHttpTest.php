<?php

namespace Tests\Feature\Api\Attachments;

use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Media;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

final class AttachmentApiHttpTest extends TestCase
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

    public function test_list_upload_download_and_delete_follow_the_contract_on_all_four_parent_types(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $this->actingAs($actor, 'web');
        $headers = $this->csrfHeaders();
        $paths = [
            '/api/v1/expenses/'.$parents['expense']->getKey().'/attachments',
            '/api/v1/expenses/'.$parents['expense']->getKey().'/rows/'.$parents['row']->getKey().'/attachments',
            '/api/v1/contracts/'.$parents['contract']->getKey().'/attachments',
            '/api/v1/projects/'.$parents['project']->getKey().'/attachments',
        ];

        foreach ($paths as $index => $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertJsonPath('data', [])
                ->assertJsonPath('abilities.upload', true)
                ->assertJsonPath('abilities.download', true)
                ->assertJsonPath('abilities.delete', true)
                ->assertJsonPath('meta.used_bytes', '0')
                ->assertJsonPath('meta.quota_bytes', '2147483648');

            $created = $this->withHeaders($headers)->post($path, [
                'file' => $this->attachmentFile('csv', 'Documento '.($index + 1).'.csv'),
            ])->assertCreated()
                ->assertJsonPath('data.name', 'Documento '.($index + 1).'.csv')
                ->assertJsonPath('data.uploaded_by.name', $actor->name)
                ->assertJsonMissingPath('data.disk')
                ->assertJsonMissingPath('data.path')
                ->assertJsonMissingPath('data.model_type')
                ->assertJsonMissingPath('data.uploaded_by.id');

            $attachmentId = (int) $created->json('data.id');
            $this->getJson($path)->assertOk()->assertJsonPath('data.0.id', $attachmentId);
            $this->get($path.'/'.$attachmentId.'/download')
                ->assertOk()
                ->assertHeader('Content-Disposition', 'attachment; filename="Documento '.($index + 1).'.csv"')
                ->assertStreamedContent("voce,importo\nDemo,10.00\n");
            $this->withHeaders($headers)->deleteJson($path.'/'.$attachmentId)
                ->assertNoContent();
            $this->assertDatabaseMissing('media', ['id' => $attachmentId]);
        }
    }

    public function test_parent_row_and_attachment_mismatches_return_not_found_without_metadata(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor] = $this->attachmentContext();
        $parents = $this->supportedAttachmentParents($tenant);
        $otherExpense = Expense::factory()->for($tenant)->create();
        $foreignExpense = Expense::factory()->for(Tenant::factory()->create())->create();
        $foreignRow = ExpenseRow::factory()->for($foreignExpense)->create(['tenant_id' => $foreignExpense->tenant_id]);
        $this->actingAs($actor, 'web');
        $headers = $this->csrfHeaders();
        $created = $this->withHeaders($headers)->post('/api/v1/expenses/'.$parents['expense']->getKey().'/attachments', [
            'file' => $this->attachmentFile('pdf', 'Segreto.pdf'),
        ])->assertCreated();
        $attachment = (int) $created->json('data.id');

        foreach ([
            '/api/v1/expenses/'.$otherExpense->getKey().'/attachments/'.$attachment.'/download',
            '/api/v1/expenses/'.$foreignExpense->getKey().'/attachments',
            '/api/v1/expenses/'.$parents['expense']->getKey().'/rows/'.$foreignRow->getKey().'/attachments',
        ] as $path) {
            $this->getJson($path)
                ->assertNotFound()
                ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
                ->assertJsonMissing(['Segreto.pdf']);
        }
    }

    public function test_permissions_validation_quota_and_missing_file_fail_visibly_without_orphans(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor] = $this->attachmentContext('100000');
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $path = '/api/v1/expenses/'.$expense->getKey().'/attachments';
        $this->actingAs($actor, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)->post($path, ['file' => $this->attachmentFile('zip')])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame(0, Media::query()->where('tenant_id', $tenant->getKey())->count());

        $created = $this->withHeaders($headers)->post($path, ['file' => $this->attachmentFile('pdf')])->assertCreated();
        $media = Media::query()->findOrFail((int) $created->json('data.id'));
        Storage::disk('attachments')->delete($media->getPathRelativeToRoot());
        $this->getJson($path.'/'.$media->getKey().'/download')
            ->assertStatus(500)->assertJsonPath('error.code', 'ATTACHMENT_FILE_MISSING');

        $tenant->update(['attachment_quota_bytes' => '0']);
        $this->withHeaders($headers)->post($path, ['file' => $this->attachmentFile('pdf')])
            ->assertUnprocessable()->assertJsonPath('error.code', 'ATTACHMENT_QUOTA_EXCEEDED');
        $this->assertSame(1, Media::query()->where('tenant_id', $tenant->getKey())->count());

        $this->revokeAttachmentAbility($actor, $tenant, 'attachment.view');
        $this->getJson($path)->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_delete_rejects_request_body_and_preserves_attachment(): void
    {
        Storage::fake('attachments');
        [$tenant, $actor] = $this->attachmentContext();
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        $path = '/api/v1/expenses/'.$expense->getKey().'/attachments';
        $this->actingAs($actor, 'web');
        $headers = $this->csrfHeaders();
        $created = $this->withHeaders($headers)->post($path, ['file' => $this->attachmentFile('pdf')])->assertCreated();
        $attachment = (int) $created->json('data.id');

        $this->withHeaders($headers)->deleteJson($path.'/'.$attachment, ['unexpected' => true])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseHas('media', ['id' => $attachment]);
    }
}
