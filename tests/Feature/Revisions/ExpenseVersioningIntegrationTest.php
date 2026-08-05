<?php

namespace Tests\Feature\Revisions;

use App\Domain\Expenses\Data\ExpenseRevisionSnapshot;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version as ApplicationVersion;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
use Tests\TestCase;

class ExpenseVersioningIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_expense_and_row_use_snapshot_versioning_with_exact_business_field_allowlists(): void
    {
        $expense = new Expense;
        $row = new ExpenseRow;

        $this->assertContains(Versionable::class, class_uses_recursive($expense));
        $this->assertContains(Versionable::class, class_uses_recursive($row));
        $this->assertSame(VersionStrategy::SNAPSHOT, $expense->getVersionStrategy());
        $this->assertSame(VersionStrategy::SNAPSHOT, $row->getVersionStrategy());

        $this->assertSame([
            'kind',
            'title',
            'notes',
            'project_id',
            'contract_id',
        ], $expense->getVersionable());
        $this->assertSame([
            'position',
            'vendor_id',
            'type',
            'description',
            'quantity',
            'unit_price',
            'entered_amount',
            'amount_includes_vat',
            'vat_rate',
            'net_amount',
            'vat_amount',
            'gross_amount',
            'is_extra',
            'funded_plafond_expense_id',
            'spend_date',
            'period_start',
            'period_end',
            'distribution',
            'external_reference',
        ], $row->getVersionable());

        foreach ([$expense, $row] as $model) {
            $this->assertSame([], array_intersect(
                ['tenant_id', 'lock_version', 'created_at', 'updated_at', 'deleted_at'],
                $model->getVersionable(),
            ));
        }
    }

    public function test_row_and_header_versions_link_to_one_shared_batch_with_distinct_versions(): void
    {
        [$actor, $context] = $this->actorContext();
        $expense = Expense::factory()->for($context->tenant)->create();
        $row = ExpenseRow::factory()->for($expense)->create();

        $expense->forceFill(['title' => 'Corrected title', 'lock_version' => $expense->lock_version + 1])->save();
        $row->forceFill(['description' => 'Corrected description', 'lock_version' => $row->lock_version + 1])->save();

        $expenseVersion = $expense->latestVersion()->firstOrFail();
        $rowVersion = $row->latestVersion()->firstOrFail();
        $this->assertInstanceOf(ApplicationVersion::class, $expenseVersion);
        $this->assertInstanceOf(ApplicationVersion::class, $rowVersion);
        $this->assertNotSame($expenseVersion->getKey(), $rowVersion->getKey());

        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            'Correct aggregate values.',
            (string) Str::uuid(),
            $expense,
            null,
        );
        app(LinkVersionToRevisionBatch::class)->execute($batch, $expenseVersion, sequence: 1);
        app(LinkVersionToRevisionBatch::class)->execute($batch, $rowVersion, sequence: 2);

        $this->assertSame(2, RevisionBatchItem::query()->where('revision_batch_id', $batch->getKey())->count());
        $this->assertDatabaseHas('revision_batch_items', [
            'revision_batch_id' => $batch->getKey(),
            'version_id' => $expenseVersion->getKey(),
            'sequence' => 1,
        ]);
        $this->assertDatabaseHas('revision_batch_items', [
            'revision_batch_id' => $batch->getKey(),
            'version_id' => $rowVersion->getKey(),
            'sequence' => 2,
        ]);
    }

    public function test_revision_snapshot_is_a_readonly_typed_dto_excluding_technical_flags(): void
    {
        $this->assertTrue(class_exists(ExpenseRevisionSnapshot::class));
        $reflection = new \ReflectionClass(ExpenseRevisionSnapshot::class);
        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());

        $snapshot = new ExpenseRevisionSnapshot(
            header: ['title' => 'Current'],
            rows: [['description' => 'Row']],
        );
        $this->assertSame(['title' => 'Current'], $snapshot->header);
        $this->assertSame([['description' => 'Row']], $snapshot->rows);
    }

    public function test_revision_data_never_enters_current_queries_and_direct_restore_is_disabled(): void
    {
        [$actor, $context] = $this->actorContext();
        $expense = Expense::factory()->for($context->tenant)->create(['title' => 'Original title']);
        $expense->forceFill(['title' => 'Updated title', 'lock_version' => $expense->lock_version + 1])->save();

        $this->assertGreaterThan(0, $expense->versions()->count());
        $current = Expense::query()->findOrFail($expense->getKey());
        $this->assertSame('Updated title', $current->title);
        $this->assertSame(1, Expense::query()->whereKey($expense->getKey())->count());

        $version = $expense->oldestVersions()->firstOrFail();
        try {
            $version->revert();
            $this->fail('Direct package restore must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('REVISION_RESTORE_INVALID', $exception->getMessage());
        }
    }

    public function test_payload_bytes_never_enter_version_or_audit_metadata(): void
    {
        [$actor, $context] = $this->actorContext();
        $expense = Expense::factory()->for($context->tenant)->create();
        $expense->forceFill(['title' => 'Changed', 'lock_version' => $expense->lock_version + 1])->save();
        $version = $expense->latestVersion()->firstOrFail();

        $serializedVersion = json_encode($version->contents, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('payload', $serializedVersion);

        $auditCount = AuditEvent::query()->count();
        $batch = app(BeginRevisionBatch::class)->execute(
            $actor,
            $context,
            RevisionOperation::Update,
            null,
            (string) Str::uuid(),
            $expense,
            null,
        );
        app(LinkVersionToRevisionBatch::class)->execute($batch, $version, sequence: 1);

        $audit = AuditEvent::query()->where('correlation_id', $batch->correlation_id)->firstOrFail();
        $serializedAudit = json_encode($audit->properties, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('payload', $serializedAudit);
        $this->assertDatabaseCount('audit_events', $auditCount + 1);
    }

    private function actorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor)];
    }
}
