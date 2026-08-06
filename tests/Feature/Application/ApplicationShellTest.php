<?php

namespace Tests\Feature\Application;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
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

    public function test_dashboard_shares_authenticated_tenant_and_permission_aware_navigation(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Shell tenant',
            'code' => 'SHELL-01',
        ]);
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Shell role '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view', 'vendor.view', 'expense.view']);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $this->actingAs($actor)
            ->get(route('operational.index'))
            ->assertOk()
            ->assertViewIs('operational.dashboard')
            ->assertViewHas('selectedYear');
    }

    public function test_operational_shell_redirects_guests_and_denies_missing_context_or_ability(): void
    {
        $this->get(route('operational.index'))->assertRedirect(route('login'));

        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);

        $this->actingAs($actor)
            ->get(route('operational.index'))
            ->assertForbidden();
    }
}
