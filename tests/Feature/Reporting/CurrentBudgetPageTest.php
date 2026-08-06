<?php

namespace Tests\Feature\Reporting;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CurrentBudgetPageTest extends TestCase
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

    public function test_current_budget_page_requires_and_exposes_its_exact_view_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Current Budget role '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['budget.view']);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $this->actingAs($actor)
            ->get(route('operational.budget.index'))
            ->assertOk()
            ->assertViewIs('operational.budget.index')
            ->assertViewHas('yearOptions');

        $unauthorized = User::factory()->for($tenant)->create(['is_active' => true]);

        $this->actingAs($unauthorized)
            ->get(route('operational.budget.index'))
            ->assertForbidden();
    }
}
