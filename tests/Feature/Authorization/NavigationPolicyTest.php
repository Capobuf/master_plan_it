<?php

namespace Tests\Feature\Authorization;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NavigationPolicyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_public_login_and_platform_tenant_navigation_are_inertia_pages(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Auth/Login'));

        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get('/platform/tenants')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Platform/Tenants/Index')
                ->where('navigation.canViewPlatformTenants', true)
                ->where('abilities.create', true)
                ->where('tenant.current', null));
    }

    public function test_tenant_actor_without_platform_ability_cannot_open_tenant_administration(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->get('/platform/tenants')
            ->assertForbidden();
    }

    public function test_platform_administrator_home_requires_or_rehydrates_a_selected_tenant(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'name' => 'Selected dashboard tenant',
            'code' => 'DASH-01',
        ]);

        $this->actingAs($administrator)
            ->get('/')
            ->assertRedirect('/platform/tenants');

        $this->withSession([EnterTenantContext::SESSION_KEY => $tenant->getKey()])
            ->get('/')
            ->assertRedirect('/operational');

        $this->get('/operational')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Dashboard')
                ->where('tenant.current.id', $tenant->getKey())
                ->where('tenant.current.name', 'Selected dashboard tenant')
                ->where('tenant.current.code', 'DASH-01'));
    }

    public function test_operational_routes_keep_the_complete_security_middleware_chain(): void
    {
        $route = Route::getRoutes()->getByName('operational.index');
        $this->assertNotNull($route);

        $middleware = $route->gatherMiddleware();

        foreach ([
            'web',
            'auth',
            'active-user',
            'tenant-context',
            'permission-team-context',
            'active-tenant',
            'tenant-presentation',
            'application-ability:dashboard.view',
        ] as $required) {
            $this->assertContains($required, $middleware);
        }
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
        ]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }
}
