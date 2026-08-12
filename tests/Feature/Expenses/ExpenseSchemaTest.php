<?php

namespace Tests\Feature\Expenses;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Services\ExpenseAggregateValidator;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ExpenseSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_annual_budget_expense_and_approval_columns_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('planning_years', [
            'budget_state', 'history_activated_at', 'lock_version',
        ]));
        $this->assertTrue(Schema::hasColumn('contracts', 'project_id'));
        $this->assertTrue(Schema::hasColumns('expenses', [
            'approved_amount', 'approved_basis', 'current_planning_row_id', 'moved_from_expense_id',
            'credit_for_expense_id',
        ]));
        $this->assertFalse(Schema::hasColumns('expenses', ['state', 'closure_outcome', 'closed_at', 'closed_by_user_id']));
        $this->assertTrue(Schema::hasColumns('approval_operations', [
            'tenant_id', 'planning_year_id', 'kind', 'effective_date', 'recorded_at',
            'actor_user_id', 'budget_basis', 'revision_batch_id', 'correlation_id',
        ]));
        $this->assertTrue(Schema::hasColumns('approval_items', [
            'approval_operation_id', 'tenant_id', 'planning_year_id', 'expense_id',
            'previous_amount', 'new_amount', 'delta_amount', 'cost_center_id',
            'project_id', 'contract_id', 'expense_kind', 'budget_basis',
        ]));
        $this->assertTrue(Schema::hasColumns('revision_batch_items', [
            'tenant_id', 'planning_year_id', 'mutation',
        ]));
    }

    public function test_business_enums_expose_the_closed_017_vocabulary(): void
    {
        $this->assertSame(['preparation', 'approved', 'closed'], array_column(BudgetState::cases(), 'value'));
        $this->assertSame(['ordinary', 'plafond'], array_column(ExpenseKind::cases(), 'value'));
        $this->assertSame(['estimate', 'quote', 'actual'], array_column(ExpenseType::cases(), 'value'));
    }

    public function test_money_columns_remain_exact_decimals_and_approved_amount_is_nullable(): void
    {
        $columns = DB::table('information_schema.columns')
            ->selectRaw('TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type, IS_NULLABLE AS is_nullable, NUMERIC_SCALE AS numeric_scale')
            ->where('table_schema', DB::getDatabaseName())
            ->whereIn('table_name', ['expense_rows', 'expenses', 'approval_items'])
            ->whereIn('column_name', [
                'quantity', 'unit_price', 'entered_amount', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount',
                'approved_amount', 'previous_amount', 'new_amount', 'delta_amount',
            ])
            ->get()
            ->keyBy(fn (object $column): string => $column->table_name.'.'.$column->column_name);

        foreach (['quantity', 'unit_price', 'entered_amount', 'vat_rate', 'net_amount', 'vat_amount', 'gross_amount'] as $name) {
            $column = $columns->get('expense_rows.'.$name);
            $this->assertNotNull($column);
            $this->assertSame('decimal', $column->data_type);
            $this->assertSame(2, (int) $column->numeric_scale);
        }
        $this->assertSame('YES', $columns->get('expenses.approved_amount')->is_nullable);
        $this->assertSame(2, (int) $columns->get('expenses.approved_amount')->numeric_scale);
        $this->assertSame('YES', $columns->get('approval_items.previous_amount')->is_nullable);
        foreach (['new_amount', 'delta_amount'] as $name) {
            $this->assertSame('NO', $columns->get('approval_items.'.$name)->is_nullable);
            $this->assertSame(2, (int) $columns->get('approval_items.'.$name)->numeric_scale);
        }
    }

    public function test_current_planning_and_links_cannot_cross_tenant_boundaries(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $expense = Expense::factory()->for($tenant)->create();
        $foreignExpense = Expense::factory()->for($foreign)->create();
        $foreignRow = ExpenseRow::factory()->for($foreignExpense)->create(['tenant_id' => $foreign->getKey()]);

        try {
            DB::table('expenses')->where('id', $expense->getKey())->update([
                'current_planning_row_id' => $foreignRow->getKey(),
            ]);
            $this->fail('A current planning row from another Tenant was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'current_planning_row_id' => null]);
        }

        try {
            DB::table('expenses')->where('id', $expense->getKey())->update([
                'moved_from_expense_id' => $foreignExpense->getKey(),
            ]);
            $this->fail('A moved-from Expense from another Tenant was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'moved_from_expense_id' => null]);
        }
    }

    public function test_validator_allows_one_optional_planning_date_and_requires_actual_date_in_year(): void
    {
        [$tenant, $year, $center, $vendor] = $this->scope();
        $validator = app(ExpenseAggregateValidator::class);
        $header = new SaveExpenseData($year->getKey(), $center->getKey(), ExpenseKind::Ordinary, 'Lifecycle', null, null, null, null);

        $planning = $this->row($vendor, ExpenseType::Estimate, null, true);
        $validated = $validator->validate($tenant, $header, [$planning]);
        $this->assertTrue($validated['rows'][0]['is_current_planning']);
        $this->assertNull($validated['rows'][0]['spend_date']);

        foreach ([null] as $date) {
            try {
                $validator->validate($tenant, $header, [$this->row($vendor, ExpenseType::Actual, $date)]);
                $this->fail('An Actual without an economic date in the planning year was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('rows.0.spend_date', $exception->errors());
            }
        }

        $actual = $validator->validate($tenant, $header, [$this->row($vendor, ExpenseType::Actual, '2025-12-31')]);
        $this->assertSame('2025-12-31', $actual['rows'][0]['spend_date']);
    }

    public function test_validator_rejects_multiple_or_actual_current_planning_rows(): void
    {
        [$tenant, $year, $center, $vendor] = $this->scope();
        $validator = app(ExpenseAggregateValidator::class);
        $header = new SaveExpenseData($year->getKey(), $center->getKey(), ExpenseKind::Ordinary, 'Selection', null, null, null, null);

        try {
            $validator->validate($tenant, $header, [
                $this->row($vendor, ExpenseType::Estimate, null, true, 1),
                $this->row($vendor, ExpenseType::Quote, null, true, 2),
            ]);
            $this->fail('Multiple current planning rows were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows', $exception->errors());
        }

        try {
            $validator->validate($tenant, $header, [$this->row($vendor, ExpenseType::Actual, '2026-01-01', true)]);
            $this->fail('An Actual was accepted as current planning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.0.is_current_planning', $exception->errors());
        }
    }

    public function test_contract_project_must_be_propagated_to_the_expense(): void
    {
        [$tenant, $year, $center, $vendor] = $this->scope();
        $project = Project::factory()->for($tenant)->for($center)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'title' => 'Contract',
            'active' => true,
            'lock_version' => 1,
        ]);
        $validator = app(ExpenseAggregateValidator::class);
        $row = $this->row($vendor, ExpenseType::Quote, null, true);

        $valid = $validator->validate($tenant, new SaveExpenseData(
            $year->getKey(), $center->getKey(), ExpenseKind::Ordinary, 'Contract expense', null,
            $project->getKey(), $contract->getKey(), null,
        ), [$row]);
        $this->assertSame($project->getKey(), $valid['header']['project_id']);

        $withoutProject = $validator->validate($tenant, new SaveExpenseData(
            $year->getKey(), $center->getKey(), ExpenseKind::Ordinary, 'Contract expense', null,
            null, $contract->getKey(), null,
        ), [$row]);
        $this->assertNull($withoutProject['header']['project_id']);
    }

    public function test_models_keep_decimal_strings_and_expose_lifecycle_relations(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['budget_state' => BudgetState::Closed]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'approved_amount' => '0.00',
        ]);
        $row = ExpenseRow::factory()->for($expense)->create(['tenant_id' => $tenant->getKey(), 'net_amount' => '100.00']);
        $expense->current_planning_row_id = $row->getKey();
        $expense->save();

        $fresh = $expense->fresh();
        $this->assertSame(BudgetState::Closed, $year->fresh()->budget_state);
        $this->assertSame('0.00', $fresh->approved_amount);
        $this->assertSame('100.00', $row->fresh()->net_amount);
        $this->assertTrue($fresh->currentPlanningRow()->is($row));
        $this->assertTrue($fresh->planningYear()->is($year));
    }

    /** @return array{Tenant, PlanningYear, CostCenter, Vendor} */
    private function scope(): array
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        return [$tenant, $year, $center, $vendor];
    }

    private function row(
        Vendor $vendor,
        ExpenseType $type,
        ?string $date,
        bool $current = false,
        int $position = 1,
    ): SaveExpenseRowData {
        return new SaveExpenseRowData(
            null, $position, $vendor->getKey(), $type, 'Row', null, null, '100.00', false,
            '22.00', false, null, $date, null, null, null, null, null, $current,
        );
    }
}
