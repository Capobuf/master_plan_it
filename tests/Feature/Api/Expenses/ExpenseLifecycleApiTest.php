<?php

namespace Tests\Feature\Api\Expenses;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ExpenseLifecycleApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_close_rejects_stale_version_without_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $expense = Expense::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$expense->getKey().'/close', [
            'lock_version' => 99,
            'outcome' => null,
        ])->assertConflict()
            ->assertJsonPath('error.code', 'STALE_VERSION');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->getKey(),
            'state' => 'open',
            'lock_version' => 1,
            'closure_outcome' => null,
            'closed_at' => null,
        ]);
    }

    public function test_close_requires_expense_update_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $expense = Expense::factory()->for($tenant)->create();
        $editor = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Editor')
            ->firstOrFail();
        $editor->revokePermissionTo('expense.update');
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$expense->getKey().'/close', [
            'lock_version' => 1,
            'outcome' => null,
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->getKey(),
            'state' => 'open',
            'lock_version' => 1,
        ]);
    }

    public function test_close_does_not_disclose_foreign_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignExpense = Expense::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$foreignExpense->getKey().'/close', [
            'lock_version' => 1,
            'outcome' => null,
        ])->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseHas('expenses', [
            'id' => $foreignExpense->getKey(),
            'state' => 'open',
            'lock_version' => 1,
        ]);
    }

    public function test_negative_actual_is_immediate_and_close_returns_final_variance(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', [
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => 'ordinary',
            'title' => 'Actual immediato',
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendor->getKey(),
                'type' => 'actual',
                'description' => 'Rettifica',
                'entered_amount' => '-20.00',
                'amount_includes_vat' => false,
                'vat_rate' => '22.00',
                'is_extra' => false,
                'spend_date' => '2026-08-01',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.actual', '-20.00')
            ->assertJsonPath('data.state', 'open');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$created->json('data.id').'/close', [
            'lock_version' => 1,
            'outcome' => null,
        ])->assertOk()
            ->assertJsonPath('data.state', 'closed')
            ->assertJsonPath('data.variance_final', true)
            ->assertJsonPath('data.actual', '-20.00');
    }
}
