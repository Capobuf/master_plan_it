<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PlafondSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_exposes_generated_live_slot_and_allocation_row_contract(): void
    {
        $this->assertTrue(Schema::hasColumn('expenses', 'active_plafond_cost_center_id'));
        $this->assertTrue(Schema::hasColumn('expense_rows', 'created_by_user_id'));

        $slot = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'expenses')
            ->where('column_name', 'active_plafond_cost_center_id')
            ->first(['EXTRA as extra', 'GENERATION_EXPRESSION as expression']);
        $this->assertNotNull($slot);
        $this->assertStringContainsString('STORED GENERATED', strtoupper((string) $slot->extra));
        $this->assertStringContainsString('plafond', strtolower((string) $slot->expression));
        $this->assertStringContainsString('deleted_at', strtolower((string) $slot->expression));

        $unique = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'expenses')
            ->where('index_name', 'expenses_live_plafond_slot_unique')
            ->where('non_unique', 0)
            ->orderBy('seq_in_index')
            ->pluck('COLUMN_NAME')
            ->all();
        $this->assertSame(
            ['tenant_id', 'planning_year_id', 'active_plafond_cost_center_id'],
            $unique,
        );

        $type = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'expense_rows')
            ->where('column_name', 'type')
            ->value('column_type');
        $this->assertStringContainsString("'allocation_adjustment'", (string) $type);
    }

    public function test_only_one_live_plafond_occupies_a_tenant_year_cost_center_slot(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();

        $first = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => ExpenseKind::Plafond,
        ]);
        $this->assertSame($center->getKey(), $first->fresh()->active_plafond_cost_center_id);

        try {
            Expense::factory()->for($tenant)->create([
                'planning_year_id' => $year->getKey(),
                'cost_center_id' => $center->getKey(),
                'kind' => ExpenseKind::Plafond,
            ]);
            $this->fail('A duplicate live Plafond occupied the same slot.');
        } catch (QueryException) {
            $this->assertSame(1, Expense::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('planning_year_id', $year->getKey())
                ->where('cost_center_id', $center->getKey())
                ->where('kind', ExpenseKind::Plafond)
                ->count());
        }

        Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => ExpenseKind::Ordinary,
        ]);
        $first->delete();
        Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => ExpenseKind::Plafond,
        ]);

        $this->assertSame(2, Expense::withTrashed()
            ->where('tenant_id', $tenant->getKey())
            ->where('planning_year_id', $year->getKey())
            ->where('cost_center_id', $center->getKey())
            ->where('kind', ExpenseKind::Plafond)
            ->count());
    }

    public function test_allocation_adjustment_requires_non_zero_amount_date_and_server_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $plafond = Expense::factory()->for($tenant)->plafond()->create();

        $row = ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create();
        $this->assertSame(ExpenseType::AllocationAdjustment, $row->fresh()->type);
        $this->assertTrue($row->fresh()->createdBy()->is($actor));

        foreach ([
            ['entered_amount' => '0.00', 'net_amount' => '0.00', 'vat_amount' => '0.00', 'gross_amount' => '0.00'],
            ['spend_date' => null],
            ['created_by_user_id' => null],
            ['funded_plafond_expense_id' => $plafond->getKey()],
            ['is_extra' => true],
        ] as $invalid) {
            try {
                ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create($invalid);
                $this->fail('An invalid AllocationAdjustment database shape was accepted.');
            } catch (QueryException) {
                $this->assertSame(
                    1,
                    ExpenseRow::query()->where('expense_id', $plafond->getKey())->count(),
                    'The rejected AllocationAdjustment must not leave a persisted row.',
                );
            }
        }
    }
}
