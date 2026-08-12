<?php

namespace Tests\Feature\Expenses;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AnnualExpenseSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_greenfield_schema_has_the_annual_expense_shape_without_expense_lifecycle_or_xor(): void
    {
        $this->assertTrue(Schema::hasColumns('tenants', ['budget_basis', 'economic_basis_locked_at']));
        $this->assertTrue(Schema::hasColumn('expense_rows', 'notes'));
        $this->assertFalse(Schema::hasColumns('expenses', ['state', 'closure_outcome', 'closed_at', 'closed_by_user_id']));

        $constraints = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ?',
            [DB::getDatabaseName(), 'expenses'],
        ))->pluck('CONSTRAINT_NAME')->implode(' ');
        $this->assertStringNotContainsString('project_contract', strtolower($constraints));
    }

    public function test_revision_batch_items_have_tenant_scoped_composite_integrity_and_diagnostic_correlation_is_not_unique(): void
    {
        $foreignKeys = collect(DB::select(
            'SELECT CONSTRAINT_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) AS columns_used
             FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND referenced_table_name = ?
             GROUP BY CONSTRAINT_NAME',
            [DB::getDatabaseName(), 'revision_batch_items', 'revision_batches'],
        ));

        $this->assertTrue($foreignKeys->contains(fn (object $key) => $key->columns_used === 'tenant_id,revision_batch_id'));
        $uniqueCorrelation = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'revision_batches')
            ->where('column_name', 'correlation_id')
            ->where('non_unique', 0)
            ->exists();
        $this->assertFalse($uniqueCorrelation);
    }
}
