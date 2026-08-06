<?php

namespace Tests\Feature\Application;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpensePagesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_expense_register_and_detail_expose_current_money_projections(): void
    {
        [$tenant, $actor] = $this->actorWithExpenseView();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2032]);
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Infrastructure']);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $costCenter->getKey(),
            'title' => 'Cloud services',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'description' => 'Managed database',
            'net_amount' => '125.00',
            'vat_amount' => '27.50',
            'gross_amount' => '152.50',
        ]);

        $this->actingAs($actor)
            ->get(route('operational.expenses.index', ['year' => $year->getKey()]))
            ->assertOk()
            ->assertViewIs('operational.expenses.index')
            ->assertViewHas('selectedYear', $year->getKey())
            ->assertViewHas('expenses', fn (array $expenses): bool => $expenses['data'][0]['id'] === $expense->getKey()
                && $expenses['data'][0]['title'] === 'Cloud services'
                && $expenses['data'][0]['net'] === '125,00 €')
            ->assertViewHas('totals', fn (array $totals): bool => $totals['gross'] === '152,50 €');

        $this->get(route('operational.expenses.show', $expense))
            ->assertOk()
            ->assertViewIs('operational.expenses.show')
            ->assertViewHas('expense', fn (array $shownExpense): bool => $shownExpense['id'] === $expense->getKey()
                && $shownExpense['rows'][0]['description'] === 'Managed database'
                && $shownExpense['gross'] === '152,50 €');
    }

    public function test_expense_routes_fail_closed_for_missing_permission_and_foreign_ids(): void
    {
        [$tenant, $actor] = $this->actorWithExpenseView();
        $foreign = Expense::factory()->for(Tenant::factory())->create();

        $this->actingAs($actor)
            ->get(route('operational.expenses.show', $foreign))
            ->assertNotFound();

        $unauthorized = User::factory()->for($tenant)->create(['is_active' => true]);
        $this->actingAs($unauthorized)
            ->get(route('operational.expenses.index'))
            ->assertForbidden();
    }

    /** @return array{Tenant, User} */
    private function actorWithExpenseView(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Application expenses '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view', 'expense.view']);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor];
    }
}
