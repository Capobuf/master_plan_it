<?php

namespace Tests\Feature\Reporting;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EconomicReportPageTest extends TestCase
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

    public function test_authorized_tenant_user_can_open_the_read_only_economic_report(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Report role '.str()->uuid(), 'guard_name' => 'web']);
        $role->syncPermissions(['report.view']);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $this->actingAs($actor)->get(route('operational.reports.index'))
            ->assertOk()
            ->assertViewIs('operational.reports.index')
            ->assertViewHas('hasEconomicData', false)
            ->assertViewHas('lines', fn (array $lines): bool => $lines['total'] === 0);
    }

    public function test_report_requires_its_exact_view_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create(['is_active' => true]);

        $this->actingAs($actor)->get(route('operational.reports.index'))->assertForbidden();
    }
}
