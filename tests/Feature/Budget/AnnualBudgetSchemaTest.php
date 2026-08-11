<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AnnualBudgetSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_annual_budget_schema_and_safe_defaults_are_active(): void
    {
        $this->assertTrue(Schema::hasColumns('planning_years', ['budget_state', 'history_activated_at', 'lock_version']));
        $this->assertTrue(Schema::hasColumns('expenses', [
            'approved_amount', 'approved_basis', 'state', 'closure_outcome', 'current_planning_row_id',
            'moved_from_expense_id', 'credit_for_expense_id', 'lock_version',
        ]));
        $this->assertTrue(Schema::hasColumns('revision_batch_items', ['tenant_id', 'planning_year_id', 'mutation']));
        $this->assertTrue(Schema::hasTable('approval_operations'));
        $this->assertTrue(Schema::hasTable('approval_items'));

        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);

        $this->assertSame(BudgetState::Preparation, $year->budget_state);
        $this->assertSame(ExpenseState::Open, $expense->state);
        $this->assertNull($expense->approved_amount);
        $this->assertSame(1, $year->lock_version);
        $this->assertSame(1, $expense->lock_version);
    }
}
