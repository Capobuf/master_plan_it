<?php

namespace Tests\Feature\Revisions;

use App\Domain\Revisions\Data\RevisionActorKind;
use App\Domain\Revisions\Data\RevisionOperation;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version as ApplicationVersion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevisionBatchSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_revision_batch_tables_hold_logical_operation_metadata_without_business_state(): void
    {
        $this->assertRevisionBatchTablesExist();

        $this->assertTrue(Schema::hasColumns('revision_batches', [
            'id',
            'tenant_id',
            'actor_user_id',
            'actor_kind',
            'root_subject_type',
            'root_subject_id',
            'operation',
            'reason',
            'correlation_id',
            'restored_from_batch_id',
            'restored_from_version_id',
            'occurred_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('revision_batch_items', [
            'id',
            'revision_batch_id',
            'version_id',
            'is_changed',
            'versionable_type',
            'versionable_id',
            'snapshot_contents',
            'operational_root_type',
            'operational_root_id',
            'sequence',
            'created_at',
            'updated_at',
        ]));

        foreach (['tenant_id', 'actor_user_id', 'actor_kind', 'root_subject_type', 'root_subject_id', 'operation', 'correlation_id', 'occurred_at'] as $column) {
            $this->assertFalse(
                $this->column('revision_batches', $column)['nullable'],
                "revision_batches.{$column} must be required.",
            );
        }

        foreach (['reason', 'restored_from_batch_id', 'restored_from_version_id'] as $column) {
            $this->assertTrue(
                $this->column('revision_batches', $column)['nullable'],
                "revision_batches.{$column} must be optional.",
            );
        }

        foreach (['revision_batch_id', 'is_changed', 'versionable_type', 'versionable_id', 'snapshot_contents', 'sequence'] as $column) {
            $this->assertFalse(
                $this->column('revision_batch_items', $column)['nullable'],
                "revision_batch_items.{$column} must be required.",
            );
        }

        foreach (['version_id', 'operational_root_type', 'operational_root_id'] as $column) {
            $this->assertTrue(
                $this->column('revision_batch_items', $column)['nullable'],
                "revision_batch_items.{$column} must support the safe legacy/retention bridge.",
            );
        }

        foreach ([
            'revision_batches' => [
                'id',
                'tenant_id',
                'actor_user_id',
                'root_subject_id',
                'restored_from_batch_id',
                'restored_from_version_id',
            ],
            'revision_batch_items' => [
                'id',
                'revision_batch_id',
                'version_id',
                'versionable_id',
            ],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertUnsignedBigInteger($table, $column);
            }
        }

        $this->assertSame('char(36)', strtolower($this->column('revision_batches', 'correlation_id')['type']));
        $this->assertSame(
            "enum('create','update','deactivate','reactivate','restore','delete')",
            strtolower($this->column('revision_batches', 'operation')['type']),
        );
        $this->assertSame(
            "enum('human','system')",
            strtolower($this->column('revision_batches', 'actor_kind')['type']),
        );
        $this->assertSame('timestamp', strtolower($this->column('revision_batches', 'occurred_at')['type_name']));
        $this->assertStringContainsString(
            'int',
            strtolower($this->column('revision_batch_items', 'sequence')['type_name']),
        );
        $this->assertStringContainsString(
            'unsigned',
            strtolower($this->column('revision_batch_items', 'sequence')['type']),
        );

        foreach ([
            'net_amount',
            'vat_amount',
            'gross_amount',
            'amount',
            'total',
            'payload',
            'properties',
            'metadata',
            'contents',
            'snapshot',
            'old_values',
            'new_values',
            'event_type',
            'event_name',
            'active',
            'lock_version',
            'deleted_at',
        ] as $column) {
            foreach (['revision_batches', 'revision_batch_items'] as $table) {
                $this->assertFalse(
                    Schema::hasColumn($table, $column),
                    "{$table} must not become an economic, event-store, payload, or current-record table ({$column}).",
                );
            }
        }
    }

    public function test_database_rejects_operations_outside_the_closed_revision_vocabulary(): void
    {
        $this->assertRevisionBatchTablesExist();

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $vendorId = $this->insertVendor($tenant->id, 'Closed operation supplier');
        $sourceVersionId = $this->insertPackageVersion($actor->id, $vendorId, 'Source supplier');

        $this->assertQueryRejected(
            fn () => $this->insertRevisionBatch(
                $tenant->id,
                $actor->id,
                $vendorId,
                $sourceVersionId,
                operation: 'publish',
            ),
        );
    }

    public function test_revision_batch_indexes_and_foreign_keys_are_tenant_safe_and_restrictive(): void
    {
        $this->assertRevisionBatchTablesExist();

        $this->assertIndex('revision_batches', ['correlation_id'], unique: true);
        $this->assertIndex('revision_batches', ['tenant_id', 'root_subject_type', 'root_subject_id', 'occurred_at']);
        $this->assertIndex('revision_batch_items', ['revision_batch_id', 'version_id'], unique: true);
        $this->assertIndex('revision_batch_items', ['revision_batch_id', 'sequence'], unique: true);
        $this->assertIndex('revision_batch_items', ['tenant_id', 'operational_root_type', 'operational_root_id', 'revision_batch_id']);

        $this->assertRestrictiveForeignKey('revision_batches', ['tenant_id'], 'tenants', ['id']);
        $this->assertRestrictiveForeignKey('revision_batches', ['actor_user_id'], 'users', ['id']);
        $this->assertRestrictiveForeignKey(
            'revision_batches',
            ['restored_from_batch_id'],
            'revision_batches',
            ['id'],
        );
        $this->assertRestrictiveForeignKey('revision_batches', ['restored_from_version_id'], 'versions', ['id']);
        $this->assertRestrictiveForeignKey(
            'revision_batch_items',
            ['revision_batch_id'],
            'revision_batches',
            ['id'],
        );
        $this->assertRestrictiveForeignKey('revision_batch_items', ['version_id'], 'versions', ['id']);
    }

    public function test_correlation_is_application_owned_and_package_versions_are_only_linked_items(): void
    {
        $this->assertRevisionBatchTablesExist();

        $this->assertFalse(
            Schema::hasColumn('versions', 'correlation_id'),
            'Correlation belongs to the application-owned revision batch, not the Overtrue package table.',
        );
        $this->assertFalse(Schema::hasColumn('versions', 'revision_batch_id'));

        $this->assertTrue(Schema::hasColumn('revision_batches', 'correlation_id'));
        $this->assertTrue(Schema::hasColumn('revision_batch_items', 'version_id'));
        $this->assertTrue(Schema::hasColumns('revision_batch_items', ['versionable_type', 'versionable_id']));
    }

    public function test_restrictive_links_preserve_revision_evidence_without_cascading_from_current_records(): void
    {
        $this->assertRevisionBatchTablesExist();

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $vendorId = $this->insertVendor($tenant->id, 'Revision evidence supplier');
        $sourceVersionId = $this->insertPackageVersion($actor->id, $vendorId, 'Source supplier');
        $linkedVersionId = $this->insertPackageVersion($actor->id, $vendorId, 'Restored supplier');
        $batchId = $this->insertRevisionBatch($tenant->id, $actor->id, $vendorId, $sourceVersionId);
        $this->insertRevisionBatchItem($batchId, $linkedVersionId, $vendorId);

        $this->assertNotSame($sourceVersionId, $linkedVersionId);
        $this->assertSame(
            $sourceVersionId,
            (int) DB::table('revision_batches')->where('id', $batchId)->value('restored_from_version_id'),
        );
        $this->assertSame(
            $linkedVersionId,
            (int) DB::table('revision_batch_items')->where('revision_batch_id', $batchId)->value('version_id'),
        );

        $this->assertQueryRejected(fn () => DB::table('revision_batches')->where('id', $batchId)->delete());
        $this->assertQueryRejected(fn () => DB::table('versions')->where('id', $sourceVersionId)->delete());
        $this->assertQueryRejected(fn () => DB::table('versions')->where('id', $linkedVersionId)->delete());
        $this->assertQueryRejected(fn () => DB::table('users')->where('id', $actor->id)->delete());
        $this->assertQueryRejected(fn () => DB::table('tenants')->where('id', $tenant->id)->delete());

        $this->assertSame(1, DB::table('vendors')->where('id', $vendorId)->delete());
        $this->assertSame(1, DB::table('revision_batches')->where('id', $batchId)->count());
        $this->assertSame(1, DB::table('revision_batch_items')->where('revision_batch_id', $batchId)->count());
    }

    public function test_revision_batch_models_expose_operation_casts_and_explicit_history_relationships(): void
    {
        foreach ([RevisionBatch::class, RevisionBatchItem::class, RevisionOperation::class, RevisionActorKind::class] as $class) {
            $this->assertTrue(class_exists($class) || enum_exists($class), "{$class} is missing.");
        }

        $batch = new RevisionBatch;
        $item = new RevisionBatchItem;
        $batch->setRawAttributes(['root_subject_type' => Vendor::class]);
        $item->setRawAttributes(['versionable_type' => Vendor::class]);

        $this->assertSame(RevisionOperation::class, $batch->getCasts()['operation'] ?? null);
        $this->assertSame(RevisionActorKind::class, $batch->getCasts()['actor_kind'] ?? null);
        $this->assertSame('datetime', $batch->getCasts()['occurred_at'] ?? null);
        $this->assertSame('integer', $item->getCasts()['sequence'] ?? null);
        $this->assertSame('array', $item->getCasts()['snapshot_contents'] ?? null);

        $this->assertBelongsTo($batch->tenant(), Tenant::class, 'tenant_id', 'id');
        $this->assertBelongsTo($batch->actor(), User::class, 'actor_user_id', 'id');
        $this->assertMorphTo($batch->rootSubject(), Vendor::class, 'root_subject_type', 'root_subject_id', 'id');
        $this->assertBelongsTo(
            $batch->restoredFromBatch(),
            RevisionBatch::class,
            'restored_from_batch_id',
            'id',
        );
        $this->assertBelongsTo(
            $batch->restoredFromVersion(),
            ApplicationVersion::class,
            'restored_from_version_id',
            'id',
        );
        $this->assertHasMany($batch->items(), RevisionBatchItem::class, 'revision_batch_id', 'id');
        $this->assertBelongsTo($item->batch(), RevisionBatch::class, 'revision_batch_id', 'id');
        $this->assertBelongsTo($item->version(), ApplicationVersion::class, 'version_id', 'id');
        $this->assertMorphTo($item->versionable(), Vendor::class, 'versionable_type', 'versionable_id', 'id');
    }

    public function test_revision_operation_has_only_the_approved_operational_values(): void
    {
        if (! enum_exists(RevisionOperation::class)) {
            $this->fail(RevisionOperation::class.' is missing.');
        }

        $this->assertSame(
            ['create', 'update', 'deactivate', 'reactivate', 'restore', 'delete'],
            array_map(
                fn (RevisionOperation $operation): string => $operation->value,
                RevisionOperation::cases(),
            ),
        );
    }

    private function assertRevisionBatchTablesExist(): void
    {
        foreach (['revision_batches', 'revision_batch_items', 'versions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is missing.");
        }
    }

    private function insertVendor(int $tenantId, string $name): int
    {
        return DB::table('vendors')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPackageVersion(int $actorId, int $vendorId, string $name): int
    {
        return DB::table('versions')->insertGetId([
            'user_id' => $actorId,
            'versionable_type' => Vendor::class,
            'versionable_id' => $vendorId,
            'contents' => json_encode(['name' => $name], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertRevisionBatch(
        int $tenantId,
        int $actorId,
        int $vendorId,
        int $versionId,
        string $operation = 'restore',
    ): int {
        return DB::table('revision_batches')->insertGetId([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'root_subject_type' => Vendor::class,
            'root_subject_id' => $vendorId,
            'operation' => $operation,
            'reason' => 'Correct an approved supplier value.',
            'correlation_id' => (string) Str::uuid(),
            'restored_from_batch_id' => null,
            'restored_from_version_id' => $versionId,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertUnsignedBigInteger(string $table, string $column): void
    {
        $definition = $this->column($table, $column);

        $this->assertSame('bigint', strtolower($definition['type_name']), "{$table}.{$column} must be BIGINT.");
        $this->assertStringContainsString(
            'unsigned',
            strtolower($definition['type']),
            "{$table}.{$column} must be unsigned.",
        );
    }

    /** @param  class-string  $related */
    private function assertBelongsTo(
        BelongsTo $relation,
        string $related,
        string $foreignKey,
        string $ownerKey,
    ): void {
        $this->assertSame($related, $relation->getRelated()::class);
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
        $this->assertSame($ownerKey, $relation->getOwnerKeyName());
    }

    /** @param  class-string  $related */
    private function assertHasMany(
        HasMany $relation,
        string $related,
        string $foreignKey,
        string $localKey,
    ): void {
        $this->assertSame($related, $relation->getRelated()::class);
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
        $this->assertSame($localKey, $relation->getLocalKeyName());
    }

    /** @param  class-string  $related */
    private function assertMorphTo(
        MorphTo $relation,
        string $related,
        string $morphType,
        string $foreignKey,
        string $ownerKey,
    ): void {
        $this->assertSame($related, $relation->getRelated()::class);
        $this->assertSame($morphType, $relation->getMorphType());
        $this->assertSame($foreignKey, $relation->getForeignKeyName());
        $this->assertSame($ownerKey, $relation->getOwnerKeyName());
    }

    private function insertRevisionBatchItem(int $batchId, int $versionId, int $vendorId): int
    {
        $tenantId = (int) DB::table('revision_batches')->where('id', $batchId)->value('tenant_id');
        $contents = (string) DB::table('versions')->where('id', $versionId)->value('contents');

        return DB::table('revision_batch_items')->insertGetId([
            'revision_batch_id' => $batchId,
            'tenant_id' => $tenantId,
            'version_id' => $versionId,
            'versionable_type' => Vendor::class,
            'versionable_id' => $vendorId,
            'snapshot_contents' => $contents,
            'operational_root_type' => Vendor::class,
            'operational_root_id' => $vendorId,
            'sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  list<string>  $columns */
    private function assertIndex(string $table, array $columns, bool $unique = false): void
    {
        $index = collect(Schema::getIndexes($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === $columns
                && (! $unique || $candidate['unique']),
        );

        $kind = $unique ? 'unique index' : 'index';
        $this->assertNotNull($index, "{$table} must have a {$kind} on ".implode(', ', $columns).'.');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    private function assertRestrictiveForeignKey(
        string $table,
        array $columns,
        string $foreignTable,
        array $foreignColumns,
    ): void {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === $columns,
        );

        $this->assertNotNull($foreignKey, "{$table} must constrain ".implode(', ', $columns).'.');
        $this->assertSame($foreignTable, $foreignKey['foreign_table']);
        $this->assertSame($foreignColumns, $foreignKey['foreign_columns']);
        $this->assertContains(strtolower($foreignKey['on_delete']), ['restrict', 'no action']);
    }

    private function assertQueryRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The database accepted a forbidden revision-history mutation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array<string, mixed> */
    private function column(string $table, string $name): array
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', $name);

        $this->assertNotNull($column, "{$table}.{$name} is missing.");

        return $column;
    }
}
