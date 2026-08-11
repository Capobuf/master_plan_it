<?php

namespace Tests\Feature\Revisions;

use App\Domain\Expenses\Actions\CloseExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\ExpenseRevisionSnapshot;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\BeginRevisionBatch;
use App\Domain\Revisions\Actions\LinkVersionToRevisionBatch;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version as ApplicationVersion;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Overtrue\LaravelVersionable\Versionable;
use Overtrue\LaravelVersionable\VersionStrategy;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseVersioningIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_expense_and_row_use_snapshot_versioning_with_exact_business_field_allowlists(): void
    {
        $expense = new Expense;
        $row = new ExpenseRow;

        $this->assertContains(Versionable::class, class_uses_recursive($expense));
        $this->assertContains(Versionable::class, class_uses_recursive($row));
        $this->assertSame(VersionStrategy::SNAPSHOT, $expense->getVersionStrategy());
        $this->assertSame(VersionStrategy::SNAPSHOT, $row->getVersionStrategy());

        $this->assertSame([
            'tenant_id',
            'kind',
            'planning_year_id',
            'cost_center_id',
            'title',
            'notes',
            'project_id',
            'contract_id',
            'approved_amount',
            'approved_basis',
            'state',
            'closure_outcome',
            'closed_at',
            'closed_by_user_id',
            'current_planning_row_id',
            'moved_from_expense_id',
            'credit_for_expense_id',
        ], $expense->getVersionable());
        $this->assertSame([
            'tenant_id',
            'expense_id',
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
            'confirmation_state',
            'confirmed_by_user_id',
            'confirmed_at',
            'is_system_managed',
            'manual_override_at',
            'contract_term_id',
            'contract_source_rule_key',
            'contract_occurrence_date',
            'source_key',
            'source_deleted_contract_id',
            'source_deleted_contract_title',
            'source_contract_deleted_at',
            'source_contract_deletion_reason',
            'source_deleted_term_id',
            'source_deleted_term_rule_key',
            'source_deleted_term_start',
            'source_deleted_term_end',
            'source_term_deleted_at',
            'source_term_deletion_reason',
        ], $row->getVersionable());

        foreach ([$expense, $row] as $model) {
            $this->assertSame([], array_intersect(
                ['created_at', 'updated_at', 'deleted_at'],
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

    public function test_close_expense_snapshots_the_lifecycle_state_change(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $expense = Expense::factory()->for($tenant)->create();
        app(CloseExpense::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            $expense,
            $expense->lock_version,
            null,
            (string) Str::uuid(),
        );

        $version = $expense->fresh()->latestVersion()->firstOrFail();
        $this->assertSame(ExpenseState::Closed->value, $version->contents['state']);
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

    public function test_noop_save_creates_no_revision_but_still_rejects_a_stale_lock(): void
    {
        [$actor, $context, $year, $center, $vendor, $expense] = $this->editableExpense();
        $row = $expense->rows()->firstOrFail();
        $batchCount = RevisionBatch::query()->count();

        $same = app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            $this->saveData($year, $center, $expense->title, $expense->notes, 1),
            [$this->saveRow($row, $vendor, 1)],
            (string) Str::uuid(),
        );

        $this->assertSame(1, $same->lock_version);
        $this->assertDatabaseCount('revision_batches', $batchCount);

        try {
            app(UpdateExpense::class)->execute(
                $actor,
                $context,
                $same,
                $this->saveData($year, $center, $same->title, $same->notes, 999),
                [$this->saveRow($row, $vendor, 1)],
                (string) Str::uuid(),
            );
            $this->fail('A stale no-op save was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }
        $this->assertDatabaseCount('revision_batches', $batchCount);
    }

    public function test_notes_only_change_creates_one_logical_revision_and_lock_only_creates_none(): void
    {
        [$actor, $context, $year, $center, $vendor, $expense] = $this->editableExpense();
        $row = $expense->rows()->firstOrFail();
        $batchCount = RevisionBatch::query()->count();

        $updated = app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            $this->saveData($year, $center, $expense->title, 'Nota significativa', 1),
            [$this->saveRow($row, $vendor, 1)],
            (string) Str::uuid(),
        );

        $this->assertSame('Nota significativa', $updated->notes);
        $this->assertDatabaseCount('revision_batches', $batchCount + 1);
        $batch = RevisionBatch::query()->latest('id')->firstOrFail();
        $this->assertSame(2, $batch->items()->count());
        $this->assertSame(1, $batch->items()->where('is_changed', true)->count());

        $updated->forceFill(['lock_version' => 3])->save();
        $this->assertDatabaseCount('revision_batches', $batchCount + 1);
        $this->assertArrayNotHasKey('lock_version', $updated->latestVersions()->firstOrFail()->contents);
    }

    /** @return array{User,TenantContext,PlanningYear,CostCenter,Vendor,Expense} */
    private function editableExpense(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $expense = app(CreateExpense::class)->execute(
            $actor,
            $context,
            $this->saveData($year, $center, 'Spesa versionata', null, null),
            [new SaveExpenseRowData(null, 1, $vendor->getKey(), ExpenseType::Estimate, 'Riga versionata', null, null, '100.00', false, '22.00', false, null, '2026-02-01', null, null, null, null, null, true)],
            (string) Str::uuid(),
        );

        return [$actor, $context, $year, $center, $vendor, $expense];
    }

    private function saveData(PlanningYear $year, CostCenter $center, string $title, ?string $notes, ?int $lock): SaveExpenseData
    {
        return new SaveExpenseData($year->getKey(), $center->getKey(), ExpenseKind::Ordinary, $title, $notes, null, null, $lock);
    }

    private function saveRow(ExpenseRow $row, Vendor $vendor, int $lock): SaveExpenseRowData
    {
        return new SaveExpenseRowData((int) $row->getKey(), 1, $vendor->getKey(), ExpenseType::Estimate, 'Riga versionata', null, null, '100.00', false, '22.00', false, null, '2026-02-01', null, null, null, null, $lock, true);
    }

    private function actorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor)];
    }
}
