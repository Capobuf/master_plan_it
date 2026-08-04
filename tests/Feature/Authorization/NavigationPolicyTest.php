<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
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
