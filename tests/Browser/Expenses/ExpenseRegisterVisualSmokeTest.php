<?php

namespace Tests\Browser\Expenses;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class ExpenseRegisterVisualSmokeTest extends DuskTestCase
{
    public function test_current_expense_register_and_detail_render_at_desktop_and_mobile_widths(): void
    {
        app(PermissionCatalogueSeeder::class)->run();

        $tenant = Tenant::factory()->create([
            'name' => 'Northwind Operations',
            'code' => 'UI-'.str()->upper(str()->random(8)),
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Cloud infrastructure']);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $costCenter->getKey(),
            'title' => 'Cloud platform services',
            'notes' => 'Current operational forecast for shared infrastructure services.',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'position' => 1,
            'description' => 'Managed database capacity',
            'net_amount' => '12500.00',
            'vat_amount' => '2750.00',
            'gross_amount' => '15250.00',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'position' => 2,
            'description' => 'Object storage and backups',
            'net_amount' => '4800.00',
            'vat_amount' => '1056.00',
            'gross_amount' => '5856.00',
        ]);
        $this->assignAbilities($actor, $tenant);

        try {
            $this->browse(function (Browser $browser) use ($actor): void {
                $browser
                    ->resize(1280, 900)
                    ->loginAs($actor)
                    ->visit('/operational')
                    ->waitForText('Dashboard')
                    ->clickLink('Expenses')
                    ->waitForText('Expense register')
                    ->assertSee('Cloud platform services')
                    ->assertSee("17'300,00 €")
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->screenshot('expense-register-desktop')
                    ->click('tbody tr')
                    ->waitForText('Managed database capacity')
                    ->assertSee('Northwind Operations')
                    ->assertSee("21'106,00 €");

                $browser->script('window.scrollTo(0, 0)');

                $browser
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->assertPresent('dl')
                    ->screenshot('expense-detail-desktop')
                    ->resize(390, 844)
                    ->visit('/operational/expenses')
                    ->waitForText('Expense register')
                    ->assertScript('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;', true)
                    ->screenshot('expense-register-mobile');
            });
        } finally {
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function assignAbilities(User $actor, Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'name' => 'Expense UI '.str()->uuid(),
            'guard_name' => 'web',
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions(['dashboard.view', 'expense.view']);
        $actor->assignRole($role);
        $registrar->forgetCachedPermissions();
    }
}
