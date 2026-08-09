<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetLifecycleApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_close_endpoint_requires_planning_year_update_and_not_expense_update(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $editor = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Editor')
            ->firstOrFail();
        $editor->revokePermissionTo('planning-year.update');
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/close', [
            'lock_version' => 1,
        ])->assertForbidden();

        $this->assertDatabaseHas('planning_years', [
            'id' => $year->getKey(),
            'budget_state' => 'preparation',
            'lock_version' => 1,
        ]);

        $editor->givePermissionTo('planning-year.update');
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/close', [
            'lock_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.budget.state', 'closed');
    }

    public function test_move_endpoint_returns_origin_and_destination_without_copying_actual_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $sourceYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $targetYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $sourceYear->getKey()]);
        $planning = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Quote,
            'spend_date' => null,
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'position' => 2,
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-02-01',
        ]);
        $expense->forceFill(['current_planning_row_id' => $planning->getKey()])->saveQuietly();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$expense->getKey().'/move', [
            'lock_version' => 1,
            'target_planning_year_id' => $targetYear->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.origin.state', 'closed')
            ->assertJsonPath('data.origin.closure_outcome', 'moved')
            ->assertJsonPath('data.destination.planning_year_id', $targetYear->getKey())
            ->assertJsonPath('data.destination.moved_from_expense_id', $expense->getKey())
            ->assertJsonCount(1, 'data.destination.rows');

        $this->assertSame('quote', $response->json('data.destination.rows.0.type'));
        $this->assertDatabaseMissing('expense_rows', [
            'expense_id' => $response->json('data.destination.id'),
            'type' => 'actual',
        ]);
    }

    public function test_expense_create_records_a_next_year_credit_link_with_only_negative_actual_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $sourceYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $targetYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $origin = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $sourceYear->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        ExpenseRow::factory()->for($origin)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-12-20',
            'entered_amount' => '100.000000',
            'net_amount' => '100.00',
        ]);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', [
            'planning_year_id' => $targetYear->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => 'ordinary',
            'title' => 'Nota di credito 2027',
            'credit_for_expense_id' => $origin->getKey(),
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendor->getKey(),
                'type' => 'actual',
                'description' => 'Nota di credito ricevuta',
                'entered_amount' => '-20.00',
                'amount_includes_vat' => false,
                'vat_rate' => '22.00',
                'is_extra' => false,
                'spend_date' => '2027-01-10',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.credit_for_expense_id', $origin->getKey())
            ->assertJsonPath('data.actual', '-20.00');

        $this->assertDatabaseHas('expense_rows', [
            'expense_id' => $response->json('data.id'),
            'type' => 'actual',
            'net_amount' => '-20.00',
        ]);
        $this->assertDatabaseHas('expense_rows', [
            'expense_id' => $origin->getKey(),
            'type' => 'actual',
            'net_amount' => '100.00',
        ]);
    }

    public function test_move_endpoint_rejects_foreign_target_year_without_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $sourceYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create(['year_label' => 2027]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $sourceYear->getKey()]);
        $planning = ExpenseRow::factory()->for($expense)->create(['tenant_id' => $tenant->getKey(), 'spend_date' => null]);
        $expense->forceFill(['current_planning_row_id' => $planning->getKey()])->saveQuietly();
        $before = Expense::query()->where('tenant_id', $tenant->getKey())->count();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$expense->getKey().'/move', [
            'lock_version' => 1,
            'target_planning_year_id' => $foreignYear->getKey(),
        ])->assertNotFound();

        $this->assertSame($before, Expense::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'state' => 'open']);
    }
}
