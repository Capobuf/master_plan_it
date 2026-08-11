<?php

namespace Tests\Feature\Api\Expenses;

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

    public function test_bulk_close_is_atomic_when_one_item_has_a_stale_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $first = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        $second = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/bulk-actions', [
            'action' => 'close',
            'planning_year_id' => $year->getKey(),
            'items' => [
                ['id' => $first->getKey(), 'lock_version' => 1],
                ['id' => $second->getKey(), 'lock_version' => 99],
            ],
            'outcome' => null,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');

        foreach ([$first, $second] as $expense) {
            $this->assertDatabaseHas('expenses', [
                'id' => $expense->getKey(),
                'state' => 'open',
                'lock_version' => 1,
                'closed_at' => null,
            ]);
        }
    }

    public function test_bulk_close_move_and_delete_reuse_the_existing_lifecycle_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year2026 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $year2027 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $this->actingAs($user, 'web');

        $closeItems = collect(range(1, 2))->map(fn (): Expense => Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2026->getKey(),
        ]));
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/bulk-actions', [
            'action' => 'close',
            'planning_year_id' => $year2026->getKey(),
            'items' => $closeItems->map(fn (Expense $expense): array => [
                'id' => $expense->getKey(), 'lock_version' => 1,
            ])->all(),
            'outcome' => null,
        ])->assertOk()
            ->assertJsonPath('data.action', 'close')
            ->assertJsonPath('data.affected_count', 2);
        foreach ($closeItems as $expense) {
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'state' => 'closed']);
        }

        $moveItems = collect(range(1, 2))->map(function () use ($tenant, $year2026): Expense {
            $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year2026->getKey()]);
            $row = ExpenseRow::factory()->for($expense)->create(['spend_date' => '2026-06-15']);
            $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

            return $expense;
        });
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/bulk-actions', [
            'action' => 'move',
            'planning_year_id' => $year2026->getKey(),
            'items' => $moveItems->map(fn (Expense $expense): array => [
                'id' => $expense->getKey(), 'lock_version' => 1,
            ])->all(),
            'target_planning_year_id' => $year2027->getKey(),
        ])->assertOk()
            ->assertJsonPath('data.action', 'move')
            ->assertJsonPath('data.affected_count', 2)
            ->assertJsonCount(2, 'data.destinations');
        foreach ($moveItems as $expense) {
            $this->assertDatabaseHas('expenses', [
                'id' => $expense->getKey(),
                'state' => 'closed',
                'closure_outcome' => 'moved',
            ]);
            $this->assertDatabaseHas('expenses', [
                'moved_from_expense_id' => $expense->getKey(),
                'planning_year_id' => $year2027->getKey(),
            ]);
        }

        $deleteItems = collect(range(1, 2))->map(fn (): Expense => Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2026->getKey(),
        ]));
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/bulk-actions', [
            'action' => 'delete',
            'planning_year_id' => $year2026->getKey(),
            'items' => $deleteItems->map(fn (Expense $expense): array => [
                'id' => $expense->getKey(), 'lock_version' => 1,
            ])->all(),
            'allow_regeneration' => false,
        ])->assertOk()
            ->assertJsonPath('data.action', 'delete')
            ->assertJsonPath('data.affected_count', 2);
        foreach ($deleteItems as $expense) {
            $this->assertSoftDeleted('expenses', ['id' => $expense->getKey()]);
        }
    }
}
