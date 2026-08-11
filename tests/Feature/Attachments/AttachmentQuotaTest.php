<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Actions\UploadAttachment;
use App\Domain\Attachments\Queries\AttachmentQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Media;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAttachmentFiles;
use Tests\Support\InteractsWithAttachments;
use Tests\TestCase;

class AttachmentQuotaTest extends TestCase
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

    public function test_quota_allows_exact_capacity_and_rejects_the_next_byte(): void
    {
        Storage::fake('attachments');
        $file = $this->attachmentFile('csv');
        $size = (int) $file->getSize();
        [$tenant, $actor, $context] = $this->attachmentContext((string) $size);
        $expense = $this->supportedAttachmentParents($tenant)['expense'];

        app(UploadAttachment::class)->execute($actor, $context, $expense, $file, (string) str()->uuid());
        $this->assertSame((string) $size, app(AttachmentQuery::class)->usageForTenant($context));

        try {
            app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('csv'), (string) str()->uuid());
            $this->fail('Quota overflow was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('ATTACHMENT_QUOTA_EXCEEDED', $exception->getMessage());
        }

        $this->assertSame(1, Media::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_zero_and_reduced_quota_reject_new_upload_without_deleting_current_media(): void
    {
        Storage::fake('attachments');
        [$zeroTenant, $zeroActor, $zeroContext] = $this->attachmentContext('0');
        $zeroExpense = $this->supportedAttachmentParents($zeroTenant)['expense'];
        $this->assertQuotaDenied(fn () => app(UploadAttachment::class)->execute($zeroActor, $zeroContext, $zeroExpense, $this->attachmentFile('pdf'), (string) str()->uuid()));

        [$tenant, $actor, $context] = $this->attachmentContext('100000');
        $expense = $this->supportedAttachmentParents($tenant)['expense'];
        app(UploadAttachment::class)->execute($actor, $context, $expense, $this->attachmentFile('pdf'), (string) str()->uuid());
        $tenant->update(['attachment_quota_bytes' => '0']);
        $this->assertQuotaDenied(fn () => app(UploadAttachment::class)->execute($actor, new TenantContext($tenant->fresh(), $actor), $expense, $this->attachmentFile('pdf'), (string) str()->uuid()));
        $this->assertSame(1, Media::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_two_upload_quota_checks_are_serialized_by_the_tenant_row_lock(): void
    {
        $firstName = 'attachment_quota_first';
        $secondName = 'attachment_quota_second';
        $connection = config('database.connections.mysql');
        config()->set("database.connections.{$firstName}", $connection);
        config()->set("database.connections.{$secondName}", $connection);
        $first = DB::connection($firstName);
        $second = DB::connection($secondName);
        $tenantId = null;

        try {
            $tenantId = $first->table('tenants')->insertGetId([
                'name' => 'Tenant lock allegati',
                'code' => 'attachment-lock-'.str()->uuid(),
                'state' => 'active',
                'currency_code' => 'EUR',
                'language_code' => 'it',
                'timezone' => 'Europe/Rome',
                'default_vat_rate' => '22.00',
                'budget_basis' => 'net',
                'attachment_quota_bytes' => 1,
                'deletion_reason_required' => false,
                'lock_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $first->beginTransaction();
            $this->assertNotNull($first->table('tenants')->where('id', $tenantId)->lockForUpdate()->first());
            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();
            try {
                $second->table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
                $this->fail('A concurrent quota check bypassed the Tenant row lock.');
            } catch (QueryException $exception) {
                $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
            } finally {
                $second->rollBack();
            }

            $first->commit();
            $second->beginTransaction();
            $this->assertNotNull($second->table('tenants')->where('id', $tenantId)->lockForUpdate()->first());
            $second->rollBack();
        } finally {
            while ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            while ($second->transactionLevel() > 0) {
                $second->rollBack();
            }
            if ($tenantId !== null) {
                $first->table('tenants')->where('id', $tenantId)->delete();
            }
            DB::purge($firstName);
            DB::purge($secondName);
        }
    }

    private function assertQuotaDenied(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Quota check unexpectedly allowed the upload.');
        } catch (DomainException $exception) {
            $this->assertSame('ATTACHMENT_QUOTA_EXCEEDED', $exception->getMessage());
        }
    }
}
