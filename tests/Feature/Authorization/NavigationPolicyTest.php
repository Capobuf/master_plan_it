<?php

namespace Tests\Feature\Authorization;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Filament\Resources\Tenants\TenantResource;
use App\Http\Middleware\ApplyOptionalTenantContext;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
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

        $panel = Filament::getPanel('admin');
        $this->assertNotNull($panel);
        Filament::setCurrentPanel($panel);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_navigation_registers_the_tenant_resource_exactly_once_only_when_its_policy_allows_view_any(): void
    {
        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
        ]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->actingAs($administrator);

        $this->assertTrue(Gate::forUser($administrator)->allows('viewAny', Tenant::class));
        $this->assertSame(
            [TenantResource::class],
            array_values(array_filter(
                $this->navigationItemKeys(),
                static fn (string $key): bool => $key === TenantResource::class,
            )),
        );
    }

    public function test_missing_exact_policy_ability_hides_navigation_without_using_a_role_name(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);
        $this->actingAs($actor);

        $this->assertFalse(Gate::forUser($actor)->allows('viewAny', Tenant::class));
        $this->assertNotContains(TenantResource::class, $this->navigationItemKeys());
    }

    public function test_direct_resource_route_independently_denies_an_actor_whose_navigation_is_hidden(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);
        $this->actingAs($actor);

        $this->assertNotContains(TenantResource::class, $this->navigationItemKeys());

        $this->get('/admin/tenants')->assertForbidden();
    }

    public function test_global_panel_routes_remain_available_without_a_selected_tenant(): void
    {
        $this->get('/admin/login')->assertOk();

        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
        ]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->actingAs($administrator);

        $this->get('/admin')->assertSuccessful();
        $this->get('/admin/tenants')->assertSuccessful();
    }

    public function test_global_dashboard_rehydrates_an_administrator_tenant_selection_for_the_shell(): void
    {
        $administrator = User::factory()->create([
            'tenant_id' => null,
            'is_active' => true,
        ]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create([
            'name' => 'Selected dashboard tenant',
            'code' => 'DASH-01',
        ]);
        $this->actingAs($administrator);

        $this->withSession([
            EnterTenantContext::SESSION_KEY => $tenant->getKey(),
        ]);

        $response = $this->get('/admin');

        $response
            ->assertSuccessful()
            ->assertSee('Selected dashboard tenant (DASH-01)')
            ->assertDontSee('No tenant selected');
    }

    public function test_panel_auth_middleware_requires_authentication_then_an_active_user(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertNotNull($panel);

        $this->assertSame([
            Authenticate::class,
            EnsureActiveUser::class,
        ], $panel->getAuthMiddleware());
        $this->assertContains(ApplyOptionalTenantContext::class, $panel->getMiddleware());
    }

    public function test_panel_persists_active_user_and_tenant_route_security_middleware_for_livewire_updates(): void
    {
        $persistentMiddleware = app(PersistentMiddleware::class)->getPersistentMiddleware();

        $this->assertContains(EnsureActiveUser::class, $persistentMiddleware);
        $this->assertContains(ResolveTenantContext::class, $persistentMiddleware);
        $this->assertContains(SetPermissionTeamContext::class, $persistentMiddleware);
        $this->assertContains(EnsureTenantIsActive::class, $persistentMiddleware);
        $this->assertContains(ApplyTenantPresentationContext::class, $persistentMiddleware);
    }

    /** @return list<string> */
    private function navigationItemKeys(): array
    {
        $panel = Filament::getCurrentPanel();
        $this->assertNotNull($panel);

        return collect($panel->getNavigation())
            ->flatMap(static function (NavigationGroup $group): array {
                $items = $group->getItems();

                return collect(is_array($items) ? $items : $items->toArray())
                    ->flatMap(static fn (NavigationItem $item): array => [
                        $item->getKey(),
                        ...collect($item->getChildItems())->map(
                            static fn (NavigationItem $child): string => $child->getKey(),
                        )->all(),
                    ])
                    ->all();
            })
            ->values()
            ->all();
    }
}
