<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenants_schema_requires_the_q_012_creation_fields(): void
    {
        $this->assertTenantTableExists();

        $this->assertTrue(Schema::hasColumns('tenants', [
            'id',
            'name',
            'code',
            'state',
            'currency_code',
            'language_code',
            'timezone',
            'default_vat_rate',
            'budget_basis',
            'attachment_quota_bytes',
            'deletion_reason_required',
            'created_by_user_id',
            'state_changed_by_user_id',
            'state_changed_at',
            'lock_version',
            'created_at',
            'updated_at',
        ]));

        foreach ([
            'name',
            'code',
            'state',
            'currency_code',
            'language_code',
            'timezone',
            'default_vat_rate',
            'budget_basis',
            'attachment_quota_bytes',
            'deletion_reason_required',
            'lock_version',
        ] as $column) {
            $this->assertFalse($this->column($column)['nullable'], "{$column} must be required.");
        }
    }

    public function test_tenant_lifecycle_evidence_is_nullable_with_restrictive_actor_foreign_keys(): void
    {
        $this->assertTenantTableExists();

        foreach ([
            'created_by_user_id',
            'state_changed_by_user_id',
            'state_changed_at',
        ] as $column) {
            $this->assertTrue($this->column($column)['nullable'], "{$column} must be nullable.");
        }

        $foreignKeys = collect(Schema::getForeignKeys('tenants'));

        foreach (['created_by_user_id', 'state_changed_by_user_id'] as $column) {
            $foreignKey = $foreignKeys->first(
                fn (array $candidate): bool => $candidate['columns'] === [$column],
            );

            $this->assertNotNull($foreignKey, "{$column} must reference users.");
            $this->assertSame('users', $foreignKey['foreign_table']);
            $this->assertSame(['id'], $foreignKey['foreign_columns']);
            $this->assertContains(strtolower($foreignKey['on_delete']), ['restrict', 'no action']);
        }
    }

    public function test_tenant_global_list_has_the_state_code_index(): void
    {
        $this->assertTenantTableExists();

        $stateCodeIndex = collect(Schema::getIndexes('tenants'))->first(
            fn (array $index): bool => $index['columns'] === ['state', 'code'],
        );

        $this->assertNotNull($stateCodeIndex, 'Tenants must have a (state, code) index.');
    }

    public function test_platform_tenant_ownership_foreign_keys_are_nullable_and_restrictive(): void
    {
        $this->assertTenantTableExists();

        $this->assertTrue($this->tableColumn('users', 'tenant_id')['nullable']);
        $this->assertRestrictiveForeignKey('users', 'tenant_id', 'tenants');

        $this->assertTrue($this->tableColumn('audit_events', 'tenant_id')['nullable']);
        $this->assertRestrictiveForeignKey('audit_events', 'tenant_id', 'tenants');
    }

    public function test_tenant_code_is_globally_unique(): void
    {
        $this->assertTenantTableExists();

        $attributes = $this->requiredTenantAttributes();
        DB::table('tenants')->insert($attributes);

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert([
            ...$attributes,
            'name' => 'Duplicate code tenant',
        ]);
    }

    public function test_tenant_defaults_match_the_approved_operational_settings(): void
    {
        $this->assertTenantTableExists();

        DB::table('tenants')->insert($this->requiredTenantAttributes());

        $tenant = DB::table('tenants')->where('code', 'schema-tenant')->firstOrFail();

        $this->assertSame('active', $tenant->state);
        $this->assertSame('net', $tenant->budget_basis);
        $this->assertSame('2147483648', (string) $tenant->attachment_quota_bytes);
        $this->assertSame(0, (int) $tenant->deletion_reason_required);
        $this->assertSame(1, (int) $tenant->lock_version);
    }

    public function test_budget_basis_defaults_to_net_and_rejects_unapproved_values(): void
    {
        $this->assertTenantTableExists();

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'gross-basis',
            'budget_basis' => 'gross',
        ]);

        $this->assertSame('gross', DB::table('tenants')
            ->where('code', 'gross-basis')
            ->value('budget_basis'));

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'invalid-basis',
            'budget_basis' => 'tax-exclusive',
        ]);
    }

    public function test_default_vat_rate_is_decimal_12_6_with_exact_string_round_trip(): void
    {
        $this->assertTenantTableExists();

        $vatColumn = $this->column('default_vat_rate');
        $this->assertSame('decimal', strtolower($vatColumn['type_name']));
        $this->assertSame('decimal(12,6)', strtolower($vatColumn['type']));

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'exact-vat-rate',
            'default_vat_rate' => '22.123456',
        ]);

        $storedVatRate = DB::table('tenants')
            ->where('code', 'exact-vat-rate')
            ->value('default_vat_rate');

        $this->assertIsString($storedVatRate);
        $this->assertSame('22.123456', $storedVatRate);
    }

    public function test_attachment_quota_is_an_unsigned_bigint_without_an_application_cap_or_float_rounding(): void
    {
        $this->assertTenantTableExists();

        $quotaColumn = $this->column('attachment_quota_bytes');
        $this->assertSame('bigint unsigned', strtolower($quotaColumn['type']));
        $this->assertSame('2147483648', (string) $quotaColumn['default']);

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'zero-quota',
            'attachment_quota_bytes' => '0',
        ]);
        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'maximum-quota',
            'attachment_quota_bytes' => '18446744073709551615',
        ]);

        $storedHighRangeQuota = DB::table('tenants')
            ->where('code', 'maximum-quota')
            ->value('attachment_quota_bytes');

        $this->assertSame('0', (string) DB::table('tenants')
            ->where('code', 'zero-quota')
            ->value('attachment_quota_bytes'));
        $this->assertIsString($storedHighRangeQuota);
        $this->assertSame('18446744073709551615', $storedHighRangeQuota);

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'negative-quota',
            'attachment_quota_bytes' => '-1',
        ]);
    }

    public function test_company_address_contact_and_report_logo_fields_are_optional(): void
    {
        $this->assertTenantTableExists();

        $optionalColumns = [
            'company_name',
            'address',
            'contact_name',
            'contact_email',
            'contact_phone',
            'report_logo_path',
        ];

        $this->assertTrue(Schema::hasColumns('tenants', $optionalColumns));

        foreach ($optionalColumns as $column) {
            $this->assertTrue($this->column($column)['nullable'], "{$column} must be optional.");
        }

        DB::table('tenants')->insert($this->requiredTenantAttributes());

        $tenant = DB::table('tenants')->where('code', 'schema-tenant')->firstOrFail();

        foreach ($optionalColumns as $column) {
            $this->assertNull($tenant->{$column});
        }
    }

    public function test_tenant_model_exposes_the_approved_exact_casts(): void
    {
        $this->assertTrue(class_exists(Tenant::class), 'Tenant model is missing.');
        $this->assertTrue(class_exists(TenantState::class), 'TenantState enum is missing.');
        $this->assertTrue(class_exists(BudgetBasis::class), 'BudgetBasis enum is missing.');

        $tenant = Tenant::query()->create([
            ...$this->requiredTenantAttributes(),
            'code' => 'model-casts',
            'state' => 'inactive',
            'budget_basis' => 'gross',
            'default_vat_rate' => '22.123456',
            'attachment_quota_bytes' => '18446744073709551615',
            'deletion_reason_required' => true,
            'lock_version' => 7,
        ]);

        $tenant->refresh();

        $this->assertInstanceOf(TenantState::class, $tenant->state);
        $this->assertSame('inactive', $tenant->state->value);
        $this->assertInstanceOf(BudgetBasis::class, $tenant->budget_basis);
        $this->assertSame('gross', $tenant->budget_basis->value);
        $this->assertIsString($tenant->default_vat_rate);
        $this->assertSame('22.123456', $tenant->default_vat_rate);
        $this->assertIsString($tenant->attachment_quota_bytes);
        $this->assertSame('18446744073709551615', $tenant->attachment_quota_bytes);
        $this->assertTrue($tenant->deletion_reason_required);
        $this->assertIsInt($tenant->lock_version);
        $this->assertSame(7, $tenant->lock_version);
    }

    /** @return array<string, mixed> */
    private function requiredTenantAttributes(): array
    {
        return [
            'name' => 'Schema tenant',
            'code' => 'schema-tenant',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function column(string $name): array
    {
        return $this->tableColumn('tenants', $name);
    }

    /** @return array<string, mixed> */
    private function tableColumn(string $table, string $name): array
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', $name);

        $this->assertNotNull($column, "{$name} is missing from {$table}.");

        return $column;
    }

    private function assertRestrictiveForeignKey(
        string $table,
        string $column,
        string $foreignTable,
    ): void {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(
            fn (array $candidate): bool => $candidate['columns'] === [$column],
        );

        $this->assertNotNull($foreignKey, "{$table}.{$column} must have a foreign key.");
        $this->assertSame($foreignTable, $foreignKey['foreign_table']);
        $this->assertSame(['id'], $foreignKey['foreign_columns']);
        $this->assertContains(strtolower($foreignKey['on_delete']), ['restrict', 'no action']);
    }

    private function assertTenantTableExists(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'), 'Tenant schema is missing.');
    }
}
