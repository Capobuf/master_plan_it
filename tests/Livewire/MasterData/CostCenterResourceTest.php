<?php

namespace Tests\Livewire\MasterData;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Filament\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Resources\CostCenters\Pages\ListCostCenters;
use App\Filament\Resources\PlanningYears\PlanningYearResource;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CostCenterResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Panel::make()->id('cost-center-resource-test'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_resource_uses_context_routes_scoped_query_and_exact_permissions(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.view', 'cost-center.create');
        $sameTenant = CostCenter::factory()->for($tenant)->create(['name' => 'Local']);
        $foreign = CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign']);
        $this->actingAs($actor);

        $this->assertContains(UsesTenantContextRoutes::class, (new ReflectionClass(CostCenterResource::class))->getTraitNames());
        $this->assertSame([
            ResolveTenantContext::class,
            SetPermissionTeamContext::class,
            EnsureTenantIsActive::class,
            ApplyTenantPresentationContext::class,
        ], CostCenterResource::getRouteMiddleware(Filament::getCurrentPanel()));
        $this->assertSame([(int) $sameTenant->getKey()], CostCenterResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains((int) $foreign->getKey(), CostCenterResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(CostCenterResource::canCreate());
        $this->assertSame($context->tenantId, CostCenterResource::tenantContext()->tenantId);
    }

    public function test_real_panel_registers_cost_center_exactly_once_after_planning_year(): void
    {
        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());
        $resources = array_values($panel->getResources());
        $planningYearIndex = array_search(PlanningYearResource::class, $resources, true);
        $costCenterIndex = array_search(CostCenterResource::class, $resources, true);

        $this->assertSame(1, count(array_keys($resources, CostCenterResource::class, true)));
        $this->assertIsInt($planningYearIndex);
        $this->assertIsInt($costCenterIndex);
        $this->assertGreaterThan($planningYearIndex, $costCenterIndex);
    }

    public function test_real_admin_panel_shows_cost_center_navigation_and_allows_direct_route_for_an_authorized_tenant_actor(): void
    {
        [, $actor] = $this->context('cost-center.view');
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertSee('Cost centers');
        $this->get('/admin/cost-centers')
            ->assertSuccessful()
            ->assertSee('Cost centers');
    }

    public function test_real_admin_panel_hides_cost_center_navigation_and_denies_direct_route_without_exact_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertDontSee('Cost centers');
        $this->get('/admin/cost-centers')->assertForbidden();
    }

    public function test_tenantless_administrator_without_selection_keeps_global_routes_but_is_forbidden_from_cost_centers(): void
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($administrator);
        $this->app['session.store']->forget(EnterTenantContext::SESSION_KEY);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertDontSee('Cost centers');
        $this->get('/admin/tenants')->assertSuccessful();
        $this->get('/admin/cost-centers')->assertForbidden();
    }

    public function test_resource_exposes_name_parent_lifecycle_delete_and_revision_controls_without_a_code_field(): void
    {
        [, $actor] = $this->context('cost-center.view', 'cost-center.create');
        $this->actingAs($actor);
        $form = CostCenterResource::form(Schema::make(app(CreateCostCenter::class)));
        $this->assertSame(['name', 'parent_id'], array_map(static fn ($component): string => $component->getName(), $form->getComponents()));

        $table = CostCenterResource::table(Table::make(app(ListCostCenters::class)));
        $this->assertSame(['name', 'parent_name', 'active'], array_values(array_map(static fn ($column): string => $column->getName(), $table->getColumns())));
        foreach (['edit', 'deactivate', 'reactivate', 'delete', 'history'] as $action) {
            $this->assertNotNull($table->getAction($action));
        }
        $this->assertArrayHasKey('edit', CostCenterResource::getPages());
        $this->assertArrayHasKey('history', CostCenterResource::getPages());
    }

    public function test_create_and_edit_pages_delegate_to_actions_with_no_direct_eloquent_writes(): void
    {
        [$tenant, $actor] = $this->context('cost-center.view', 'cost-center.create', 'cost-center.update');
        $this->actingAs($actor);
        $created = $this->invoke(new CreateCostCenter, 'handleRecordCreation', ['name' => 'Resource center', 'parent_id' => null]);
        $this->assertInstanceOf(CostCenter::class, $created);
        $this->assertSame($tenant->getKey(), $created->tenant_id);
        request()->attributes->remove(CorrelationId::class);
        $this->app->forgetInstance(CorrelationId::class);
        $updated = $this->invoke(new EditCostCenter, 'handleRecordUpdate', $created, ['name' => 'Updated center', 'parent_id' => null]);
        $this->assertSame('Updated center', $updated->name);

        $source = '';
        foreach ([
            app_path('Filament/Resources/CostCenters/CostCenterResource.php'),
            app_path('Filament/Resources/CostCenters/Pages/ListCostCenters.php'),
            app_path('Filament/Resources/CostCenters/Pages/CreateCostCenter.php'),
            app_path('Filament/Resources/CostCenters/Pages/EditCostCenter.php'),
            app_path('Filament/Resources/CostCenters/Pages/CostCenterRevisionHistory.php'),
        ] as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $source .= $contents;
        }
        $this->assertStringContainsString('CreateCostCenter', $source);
        $this->assertStringContainsString('UpdateCostCenter', $source);
        $this->assertStringContainsString('DeleteCostCenter', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:(?:CostCenter)::(?:create|forceCreate|updateOrCreate|insert|upsert|destroy)|DB::(?:table|statement|insert|update|delete)|->(?:forceCreate|update|updateOrCreate|save|saveQuietly|delete|deleteQuietly|forceDelete|insert|upsert|forceFill))\s*\(/',
            $source,
        );
    }

    public function test_create_page_rejects_foreign_missing_and_soft_deleted_parent_ids_without_normalizing_to_root(): void
    {
        [$tenant, $actor] = $this->context('cost-center.view', 'cost-center.create');
        $this->actingAs($actor);
        $foreign = CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign parent']);
        $softDeleted = CostCenter::factory()->for($tenant)->create(['name' => 'Deleted parent']);
        $softDeleted->delete();

        foreach ([$foreign->getKey(), 999999999, $softDeleted->getKey()] as $index => $parentId) {
            $name = 'Invalid parent create '.$index;
            $this->assertDomainCode(
                fn (): mixed => $this->invoke(new CreateCostCenter, 'handleRecordCreation', ['name' => $name, 'parent_id' => $parentId]),
                'TENANT_RELATION_MISMATCH',
            );
            $this->assertDatabaseMissing('cost_centers', ['tenant_id' => $tenant->getKey(), 'name' => $name]);
        }
    }

    public function test_edit_page_rejects_foreign_missing_and_soft_deleted_parent_ids_without_normalizing_to_root(): void
    {
        [$tenant, $actor] = $this->context('cost-center.view', 'cost-center.update');
        $this->actingAs($actor);
        $record = CostCenter::factory()->for($tenant)->create(['name' => 'Edit target', 'lock_version' => 4]);
        $foreign = CostCenter::factory()->for(Tenant::factory())->create(['name' => 'Foreign edit parent']);
        $softDeleted = CostCenter::factory()->for($tenant)->create(['name' => 'Deleted edit parent']);
        $softDeleted->delete();

        foreach ([$foreign->getKey(), 999999998, $softDeleted->getKey()] as $parentId) {
            $this->assertDomainCode(
                fn (): mixed => $this->invoke(new EditCostCenter, 'handleRecordUpdate', $record, ['name' => 'Mutated edit target', 'parent_id' => $parentId]),
                'TENANT_RELATION_MISMATCH',
            );
            $this->assertDatabaseHas('cost_centers', [
                'id' => $record->getKey(),
                'name' => 'Edit target',
                'parent_id' => null,
                'lock_version' => 4,
            ]);
        }
    }

    /** @return array{Tenant, User, TenantContext} */
    private function context(string ...$abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);
        $this->app->instance(TenantContext::class, $context);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        foreach ($abilities as $ability) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cost center resource '.$ability,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($ability);
            $actor->assignRole($role);
        }
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor, $context];
    }

    private function invoke(object $page, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($page, $method))->invoke($page, ...$arguments);
    }

    private function assertDomainCode(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Expected domain code [{$code}].");
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }
}
