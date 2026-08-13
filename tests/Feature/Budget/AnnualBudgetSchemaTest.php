<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\BudgetClosure;
use App\Models\BudgetRectification;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AnnualBudgetSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_greenfield_schema_contains_only_the_target_approval_aggregate_and_lifecycle_seams(): void
    {
        $this->assertTrue(Schema::hasColumns('planning_years', ['budget_state', 'history_activated_at', 'lock_version']));
        $this->assertTrue(Schema::hasColumns('expenses', [
            'current_planning_row_id', 'moved_from_expense_id', 'credit_for_expense_id', 'lock_version',
        ]));
        $this->assertFalse(Schema::hasColumns('expenses', ['approved_amount', 'approved_basis']));
        $this->assertTrue(Schema::hasTable('budget_approvals'));
        $this->assertTrue(Schema::hasTable('budget_approval_items'));
        $this->assertTrue(Schema::hasTable('budget_rectifications'));
        $this->assertTrue(Schema::hasTable('budget_closures'));
        $this->assertFalse(Schema::hasTable('approval_operations'));
        $this->assertFalse(Schema::hasTable('approval_items'));

        $this->assertTrue(Schema::hasColumns('budget_approvals', [
            'status', 'effective_date', 'recorded_at', 'approved_by_user_id', 'approved_by_name',
            'approval_note', 'currency_code', 'budget_basis', 'total_net_amount', 'total_vat_amount',
            'total_gross_amount', 'total_official_amount', 'contributor_count',
            'composition_schema_version', 'projection_version', 'composition_fingerprint',
            'approval_revision_batch_id', 'correlation_id', 'annulled_at', 'annulled_by_user_id',
            'annulled_by_name', 'annulment_note', 'annulment_revision_batch_id',
            'annulment_correlation_id', 'active_planning_year_id',
        ]));
        $this->assertTrue(Schema::hasColumns('budget_approval_items', [
            'source_identity', 'source_lock_version', 'component_kind', 'expense_id', 'expense_row_id',
            'expense_title', 'row_description', 'cost_center_id', 'cost_center_name', 'vendor_id',
            'vendor_name', 'project_id', 'project_title', 'contract_id', 'contract_title',
            'net_amount', 'vat_amount', 'gross_amount', 'official_amount',
        ]));

        $this->assertSame(
            ['tenant_id', 'active_planning_year_id'],
            $this->indexColumns('budget_approvals', 'budget_approvals_active_year_unique', true),
        );
        $this->assertSame(['correlation_id'], $this->indexColumns('budget_approvals', 'budget_approvals_correlation_idx', false));
        $this->assertSame(['annulment_correlation_id'], $this->indexColumns('budget_approvals', 'budget_approvals_annul_correlation_idx', false));
        $this->assertSame(
            ['tenant_id', 'type', 'expense_id', 'deleted_at', 'id'],
            $this->indexColumns('expense_rows', 'expense_rows_actual_blocker_idx', false),
        );
        $this->assertSame(
            ['tenant_id', 'is_extra', 'expense_id', 'deleted_at', 'id'],
            $this->indexColumns('expense_rows', 'expense_rows_extra_blocker_idx', false),
        );
        $this->assertSame(
            ['tenant_id', 'planning_year_id', 'id', 'deleted_at'],
            $this->indexColumns('expenses', 'expenses_budget_blocker_idx', false),
        );
    }

    public function test_active_slot_is_unique_per_tenant_year_but_allows_history_and_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $yearA = PlanningYear::factory()->for($tenantA)->create();
        BudgetApproval::factory()->for($tenantA)->for($yearA, 'planningYear')->create();

        try {
            BudgetApproval::factory()->for($tenantA)->for($yearA, 'planningYear')->create();
            $this->fail('A second active approval in the same Tenant/Year must be rejected.');
        } catch (QueryException) {
            $this->assertDatabaseCount('budget_approvals', 1);
        }

        BudgetApproval::factory()->for($tenantA)->for($yearA, 'planningYear')->annulled()->count(2)->create();
        $tenantB = Tenant::factory()->create();
        $yearB = PlanningYear::factory()->for($tenantB)->create(['year_label' => $yearA->year_label]);
        BudgetApproval::factory()->for($tenantB)->for($yearB, 'planningYear')->create();

        $this->assertSame(3, BudgetApproval::query()->where('tenant_id', $tenantA->getKey())->count());
        $this->assertSame(1, BudgetApproval::query()->where('tenant_id', $tenantB->getKey())->count());
    }

    public function test_status_amount_count_fingerprint_and_item_kind_constraints_are_database_enforced(): void
    {
        $approval = BudgetApproval::factory()->create();
        $approval->refresh();
        $this->assertSame(BudgetApprovalStatus::Active, $approval->status);
        $this->assertSame($approval->planning_year_id, $approval->active_planning_year_id);

        $invalidHeaders = [
            ['contributor_count' => 0],
            ['total_gross_amount' => '123.00'],
            ['total_official_amount' => '122.00'],
            ['composition_fingerprint' => 'not-a-fingerprint'],
        ];
        foreach ($invalidHeaders as $attributes) {
            try {
                BudgetApproval::factory()->for($approval->tenant)->for($approval->planningYear, 'planningYear')
                    ->annulled()->create($attributes);
                $this->fail('Invalid approval headers must be rejected by MySQL.');
            } catch (QueryException) {
                continue;
            }
        }

        try {
            BudgetApproval::factory()->for($approval->tenant)->for($approval->planningYear, 'planningYear')
                ->create(['status' => 'annulled']);
            $this->fail('An annulled header without terminal metadata must be rejected by MySQL.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('budget_approvals', [
                'tenant_id' => $approval->tenant_id,
                'planning_year_id' => $approval->planning_year_id,
                'status' => 'annulled',
                'annulled_at' => null,
            ]);
        }

        BudgetApprovalItem::factory()->for($approval, 'approval')->create();
        foreach ([
            ['source_lock_version' => 0],
            ['gross_amount' => '123.00'],
            ['official_amount' => '122.00'],
            ['component_kind' => 'ordinary_current_planning', 'expense_kind' => 'plafond'],
            ['component_kind' => 'plafond_allocation', 'expense_kind' => 'plafond', 'expense_row_id' => 501],
        ] as $attributes) {
            try {
                BudgetApprovalItem::factory()->for($approval, 'approval')->create([
                    'source_identity' => 'expense-row:'.fake()->unique()->numberBetween(1000, 999999),
                    ...$attributes,
                ]);
                $this->fail('Invalid approval items must be rejected by MySQL.');
            } catch (QueryException) {
                continue;
            }
        }
    }

    public function test_snapshot_source_ids_are_historical_copies_while_parent_scope_is_composite(): void
    {
        $approval = BudgetApproval::factory()->create();
        $item = BudgetApprovalItem::factory()->for($approval, 'approval')->create([
            'expense_id' => 900000001,
            'expense_row_id' => 900000002,
            'source_identity' => 'expense-row:900000002',
            'cost_center_id' => 900000003,
            'vendor_id' => 900000004,
            'vendor_name' => 'Fornitore storico',
        ]);
        $this->assertDatabaseHas('budget_approval_items', ['id' => $item->getKey(), 'expense_id' => 900000001]);

        $foreignTenant = Tenant::factory()->create();
        try {
            BudgetApprovalItem::query()->insert([
                ...$item->getAttributes(),
                'id' => null,
                'tenant_id' => $foreignTenant->getKey(),
                'source_identity' => 'expense-row:900000099',
            ]);
            $this->fail('Snapshot items cannot cross the aggregate Tenant boundary.');
        } catch (QueryException) {
            $this->assertDatabaseCount('budget_approval_items', 1);
        }
    }

    public function test_rectification_and_closure_seams_are_narrow_append_only_identities_with_nonunique_correlations(): void
    {
        $approval = BudgetApproval::factory()->create();
        $correlationId = (string) str()->uuid();

        BudgetRectification::factory()->for($approval, 'approval')->count(2)->create(['correlation_id' => $correlationId]);
        BudgetClosure::factory()->for($approval, 'approval')->count(2)->create(['correlation_id' => $correlationId]);

        $this->assertDatabaseCount('budget_rectifications', 2);
        $this->assertDatabaseCount('budget_closures', 2);
        $this->assertFalse(Schema::hasColumns('budget_rectifications', ['net_amount', 'gross_amount', 'payload']));
        $this->assertFalse(Schema::hasColumns('budget_closures', ['net_amount', 'gross_amount', 'reopened_at', 'payload']));
        $this->assertSame(['correlation_id'], $this->indexColumns('budget_rectifications', 'budget_rectifications_correlation_idx', false));
        $this->assertSame(['correlation_id'], $this->indexColumns('budget_closures', 'budget_closures_correlation_idx', false));
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index, bool $unique): array
    {
        $rows = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->orderBy('seq_in_index')
            ->get([DB::raw('COLUMN_NAME AS column_name'), DB::raw('NON_UNIQUE AS non_unique')]);

        $this->assertNotEmpty($rows, "Missing index {$index} on {$table}.");
        $this->assertSame($unique ? 0 : 1, (int) $rows->first()->non_unique);

        return $rows->pluck('column_name')->map(static fn ($column): string => (string) $column)->all();
    }
}
