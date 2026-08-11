<?php

namespace Tests\Feature\MasterData;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Database\Factories\CostCenterFactory;
use Database\Factories\PlanningYearFactory;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_master_data_tables_use_the_approved_tenant_owned_schema(): void
    {
        $this->assertMasterDataTablesExist();

        $this->assertTrue(Schema::hasColumns('planning_years', [
            'id',
            'tenant_id',
            'year_label',
            'active',
            'lock_version',
            'created_at',
            'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('planning_years', 'start_date'));
        $this->assertFalse(Schema::hasColumn('planning_years', 'end_date'));
        $this->assertFalse(Schema::hasColumn('planning_years', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('cost_centers', [
            'id',
            'tenant_id',
            'parent_id',
            'name',
            'active',
            'lock_version',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('cost_centers', 'code'));
        $this->assertFalse(Schema::hasColumn('cost_centers', 'depth'));
        $this->assertFalse(Schema::hasColumn('cost_centers', 'lft'));
        $this->assertFalse(Schema::hasColumn('cost_centers', 'rgt'));

        $this->assertTrue(Schema::hasColumns('vendors', [
            'id',
            'tenant_id',
            'name',
            'vat_number',
            'email',
            'phone',
            'address',
            'active',
            'lock_version',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));

        foreach (['planning_years', 'cost_centers', 'vendors'] as $table) {
            foreach (['tenant_id', 'active', 'lock_version'] as $column) {
                $this->assertFalse(
                    $this->column($table, $column)['nullable'],
                    "{$table}.{$column} must be required.",
                );
            }

            $this->assertSame(1, (int) $this->column($table, 'active')['default']);
            $this->assertSame(1, (int) $this->column($table, 'lock_version')['default']);
            $this->assertStringContainsString(
                'unsigned',
                strtolower($this->column($table, 'lock_version')['type']),
            );
        }

        $this->assertFalse($this->column('planning_years', 'year_label')['nullable']);
        $this->assertStringContainsString(
            'int',
            strtolower($this->column('planning_years', 'year_label')['type_name']),
        );
        $this->assertTrue($this->column('cost_centers', 'parent_id')['nullable']);

        foreach (['vat_number', 'email', 'phone', 'address'] as $column) {
            $this->assertTrue(
                $this->column('vendors', $column)['nullable'],
                "vendors.{$column} must be optional.",
            );
        }

        foreach (['cost_centers', 'vendors'] as $table) {
            $this->assertTrue($this->column($table, 'deleted_at')['nullable']);
        }
    }

    public function test_master_data_indexes_and_foreign_keys_are_scoped_and_restrictive(): void
    {
        $this->assertMasterDataTablesExist();

        $this->assertIndex('planning_years', ['tenant_id', 'year_label'], unique: true);
        $this->assertIndex('planning_years', ['tenant_id', 'active', 'year_label']);
        $this->assertIndex('cost_centers', ['tenant_id', 'name'], unique: true);
        $this->assertIndex('cost_centers', ['tenant_id', 'parent_id', 'active']);
        $this->assertIndex('vendors', ['tenant_id', 'name'], unique: true);
        $this->assertIndex('vendors', ['tenant_id', 'active', 'name']);

        foreach (['planning_years', 'cost_centers', 'vendors'] as $table) {
            $this->assertIndex($table, ['tenant_id', 'id']);
            $this->assertRestrictiveForeignKey($table, ['tenant_id'], 'tenants', ['id']);
        }

        $this->assertRestrictiveForeignKey(
            'cost_centers',
            ['tenant_id', 'parent_id'],
            'cost_centers',
            ['tenant_id', 'id'],
        );
    }

    public function test_master_data_identities_are_unique_only_inside_one_tenant(): void
    {
        $this->assertMasterDataTablesExist();

        [$tenantA, $tenantB] = $this->createTenants();

        $this->insertPlanningYear($tenantA->id, 2026);
        $this->insertPlanningYear($tenantB->id, 2026);
        $this->assertQueryRejected(fn () => $this->insertPlanningYear($tenantA->id, 2026));

        $this->insertCostCenter($tenantA->id, 'Operations');
        $this->insertCostCenter($tenantB->id, 'Operations');
        $this->assertQueryRejected(fn () => $this->insertCostCenter($tenantA->id, 'Operations'));

        $this->insertVendor($tenantA->id, 'Supplier');
        $this->insertVendor($tenantB->id, 'Supplier');
        $this->assertQueryRejected(fn () => $this->insertVendor($tenantA->id, 'Supplier'));
    }

    public function test_cost_center_adjacency_supports_three_levels_and_rejects_foreign_tenant_parents(): void
    {
        $this->assertMasterDataTablesExist();

        [$tenantA, $tenantB] = $this->createTenants();

        $rootId = $this->insertCostCenter($tenantA->id, 'Root');
        $childId = $this->insertCostCenter($tenantA->id, 'Child', $rootId);
        $grandchildId = $this->insertCostCenter($tenantA->id, 'Grandchild', $childId);

        $this->assertSame($rootId, (int) DB::table('cost_centers')->where('id', $childId)->value('parent_id'));
        $this->assertSame($childId, (int) DB::table('cost_centers')->where('id', $grandchildId)->value('parent_id'));

        $this->assertQueryRejected(
            fn () => $this->insertCostCenter($tenantB->id, 'Foreign child', $rootId),
        );
    }

    public function test_restrictive_references_prevent_cascade_and_preserve_constrained_delete_support(): void
    {
        $this->assertMasterDataTablesExist();

        $tenant = Tenant::factory()->create();
        $planningYearId = $this->insertPlanningYear($tenant->id, 2026);
        $rootId = $this->insertCostCenter($tenant->id, 'Root');
        $childId = $this->insertCostCenter($tenant->id, 'Child', $rootId);
        $vendorId = $this->insertVendor($tenant->id, 'Supplier');

        $this->assertQueryRejected(fn () => DB::table('tenants')->where('id', $tenant->id)->delete());
        $this->assertQueryRejected(fn () => DB::table('cost_centers')->where('id', $rootId)->delete());

        $this->assertSame(1, DB::table('planning_years')->where('id', $planningYearId)->count());
        $this->assertSame(1, DB::table('cost_centers')->where('id', $childId)->count());
        $this->assertSame(1, DB::table('vendors')->where('id', $vendorId)->count());

        $this->assertSame(1, DB::table('vendors')->where('id', $vendorId)->delete());
        $this->assertSame(0, DB::table('vendors')->where('id', $vendorId)->count());
    }

    public function test_models_and_factories_expose_tenant_relations_active_state_and_optimistic_casts(): void
    {
        foreach ([
            PlanningYear::class,
            CostCenter::class,
            Vendor::class,
            PlanningYearFactory::class,
            CostCenterFactory::class,
            VendorFactory::class,
        ] as $class) {
            $this->assertTrue(class_exists($class), "{$class} is missing.");
        }

        $tenant = Tenant::factory()->create();
        $planningYear = PlanningYear::factory()->for($tenant)->create([
            'year_label' => 2026,
            'active' => false,
            'lock_version' => 7,
        ]);
        $root = CostCenter::factory()->for($tenant)->create([
            'name' => 'Root',
            'active' => false,
            'lock_version' => 8,
        ]);
        $child = CostCenter::factory()->for($tenant)->for($root, 'parent')->create([
            'name' => 'Child',
        ]);
        $vendor = Vendor::factory()->for($tenant)->create([
            'name' => 'Supplier',
            'active' => false,
            'lock_version' => 9,
        ]);

        $this->assertSame($tenant->id, $planningYear->tenant->id);
        $this->assertSame($tenant->id, $root->tenant->id);
        $this->assertSame($tenant->id, $vendor->tenant->id);
        $this->assertSame($root->id, $child->parent->id);
        $this->assertTrue($root->children->contains($child));

        $this->assertIsInt($planningYear->year_label);
        $this->assertFalse($planningYear->active);
        $this->assertFalse($root->active);
        $this->assertFalse($vendor->active);
        $this->assertSame(7, $planningYear->lock_version);
        $this->assertSame(8, $root->lock_version);
        $this->assertSame(9, $vendor->lock_version);

        $this->assertContains(SoftDeletes::class, class_uses_recursive(CostCenter::class));
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Vendor::class));
        $this->assertNotContains(SoftDeletes::class, class_uses_recursive(PlanningYear::class));

        try {
            $planningYear->delete();
            $this->fail('Planning years must not support permanent deletion.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array{Tenant, Tenant} */
    private function createTenants(): array
    {
        return [Tenant::factory()->create(), Tenant::factory()->create()];
    }

    private function insertPlanningYear(int $tenantId, int $year): int
    {
        return DB::table('planning_years')->insertGetId([
            'tenant_id' => $tenantId,
            'year_label' => $year,
            'active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCostCenter(int $tenantId, string $name, ?int $parentId = null): int
    {
        return DB::table('cost_centers')->insertGetId([
            'tenant_id' => $tenantId,
            'parent_id' => $parentId,
            'name' => $name,
            'active' => true,
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            $this->fail('The database accepted a forbidden master-data mutation.');
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

    private function assertMasterDataTablesExist(): void
    {
        foreach (['planning_years', 'cost_centers', 'vendors'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is missing.");
        }
    }
}
