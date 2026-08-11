<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\ApprovalOperation;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetApprovalApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_approval_preserves_unselected_null_and_records_selected_zero(): void
    {
        [$tenant, $year, $first, $second] = $this->fixture();
        $user = $this->tenantUser($tenant);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', [
            'budget_lock_version' => 1,
            'effective_date' => '2026-02-10',
            'reason' => 'Approve only the first expense at zero',
            'items' => [[
                'expense_id' => $first->getKey(),
                'expense_lock_version' => 1,
                'approved_amount' => '0.00',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.budget.state', 'approved')
            ->assertJsonPath('data.summary.initial_approved', '0.00')
            ->assertJsonPath('data.expenses.0.approved', '0.00')
            ->assertJsonPath('data.expenses.1.approved', null);

        $this->assertDatabaseHas('expenses', ['id' => $first->getKey(), 'approved_amount' => '0.00']);
        $this->assertDatabaseHas('expenses', ['id' => $second->getKey(), 'approved_amount' => null]);
        $operation = ApprovalOperation::query()->with('items')->sole();
        $this->assertSame('initial', $operation->kind->value);
        $this->assertCount(1, $operation->items);
        $this->assertSame('0.00', $operation->items->sole()->new_amount);
    }

    public function test_stale_item_rejects_the_entire_multi_expense_variation(): void
    {
        [$tenant, $year, $first, $second] = $this->fixture();
        $user = $this->tenantUser($tenant);
        $this->actingAs($user, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', [
            'budget_lock_version' => 1,
            'effective_date' => '2026-02-10',
            'items' => [[
                'expense_id' => $first->getKey(),
                'expense_lock_version' => 1,
                'approved_amount' => '100.00',
            ]],
        ])->assertOk();

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', [
            'budget_lock_version' => 2,
            'effective_date' => '2026-03-01',
            'items' => [
                ['expense_id' => $first->getKey(), 'expense_lock_version' => 2, 'approved_amount' => '80.00'],
                ['expense_id' => $second->getKey(), 'expense_lock_version' => 99, 'approved_amount' => '20.00'],
            ],
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');

        $this->assertDatabaseHas('planning_years', ['id' => $year->getKey(), 'lock_version' => 2]);
        $this->assertDatabaseHas('expenses', ['id' => $first->getKey(), 'approved_amount' => '100.00', 'lock_version' => 2]);
        $this->assertDatabaseHas('expenses', ['id' => $second->getKey(), 'approved_amount' => null, 'lock_version' => 1]);
        $this->assertDatabaseCount('approval_operations', 1);
    }

    public function test_approval_denies_missing_ability_without_side_effects(): void
    {
        [$tenant, $year, $first] = $this->fixture();
        $unauthorized = User::factory()->for($tenant)->create(['is_active' => true]);
        $this->actingAs($unauthorized, 'web');
        $payload = [
            'budget_lock_version' => 1,
            'effective_date' => '2026-02-10',
            'items' => [[
                'expense_id' => $first->getKey(),
                'expense_lock_version' => 1,
                'approved_amount' => '10.00',
            ]],
        ];

        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', $payload)
            ->assertForbidden();
        $this->assertDatabaseCount('approval_operations', 0);
    }

    public function test_approval_does_not_disclose_a_foreign_expense(): void
    {
        [$tenant, $year] = $this->fixture();
        $authorized = $this->tenantUser($tenant);
        $foreign = Expense::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($authorized, 'web');
        $payload = [
            'budget_lock_version' => 1,
            'effective_date' => '2026-02-10',
            'items' => [[
                'expense_id' => $foreign->getKey(),
                'expense_lock_version' => 1,
                'approved_amount' => '10.00',
            ]],
        ];

        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', $payload)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->assertDatabaseCount('approval_operations', 0);
    }

    /** @return array{Tenant, PlanningYear, Expense, Expense} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $first = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'title' => 'First']);
        $second = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'title' => 'Second']);
        foreach ([$first, $second] as $expense) {
            $row = ExpenseRow::factory()->for($expense)->create([
                'tenant_id' => $tenant->getKey(),
                'type' => ExpenseType::Estimate,
                'spend_date' => null,
            ]);
            $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        }

        return [$tenant, $year, $first, $second];
    }
}
