<?php

namespace Tests\Livewire\MasterData;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\PlanningYears\Pages\CreatePlanningYear as CreatePlanningYearPage;
use App\Filament\Resources\PlanningYears\Pages\ListPlanningYears;
use App\Filament\Resources\PlanningYears\PlanningYearResource;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
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

class PlanningYearResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Panel::make()->id('planning-year-resource-test'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_resource_uses_tenant_routes_scoped_query_and_exact_create_permission(): void
    {
        [$tenant, $actor, $context] = $this->contextWithAbilities('planning-year.view', 'planning-year.create');
        $sameTenant = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $foreign = PlanningYear::factory()->for(Tenant::factory())->create(['year_label' => 2025]);
        $this->actingAs($actor);

        $this->assertContains(UsesTenantContextRoutes::class, (new ReflectionClass(PlanningYearResource::class))->getTraitNames());
        $this->assertSame([
            ResolveTenantContext::class,
            SetPermissionTeamContext::class,
            EnsureTenantIsActive::class,
            ApplyTenantPresentationContext::class,
        ], PlanningYearResource::getRouteMiddleware(Filament::getCurrentPanel()));
        $this->assertSame([(int) $sameTenant->getKey()], PlanningYearResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains((int) $foreign->getKey(), PlanningYearResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(PlanningYearResource::canCreate());
        $this->assertSame($context->tenantId, PlanningYearResource::tenantContext()->tenantId);
    }

    public function test_real_admin_panel_registers_the_planning_year_resource_exactly_once(): void
    {
        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());

        $this->assertSame('admin', $panel->getId());
        $this->assertSame(
            1,
            count(array_keys($panel->getResources(), PlanningYearResource::class, true)),
            'T002-009 must register PlanningYearResource exactly once in the real admin panel.',
        );
    }

    public function test_real_admin_panel_shows_navigation_and_allows_the_direct_route_for_an_authorized_tenant_actor(): void
    {
        [, $actor] = $this->contextWithAbilities('planning-year.view');
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertSee('Planning years');
        $this->get('/admin/planning-years')
            ->assertSuccessful()
            ->assertSee('Planning years');
    }

    public function test_real_admin_panel_hides_navigation_and_denies_the_direct_route_without_the_exact_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertDontSee('Planning years');
        $this->get('/admin/planning-years')->assertForbidden();
    }

    public function test_tenantless_administrator_without_selection_keeps_global_admin_routes_but_not_planning_year_access(): void
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($administrator);
        $this->app['session.store']->forget(EnterTenantContext::SESSION_KEY);

        $this->get('/admin')
            ->assertSuccessful()
            ->assertDontSee('Planning years');
        $this->get('/admin/tenants')->assertSuccessful();
        $this->get('/admin/planning-years')->assertForbidden();
    }

    public function test_resource_exposes_only_calendar_identity_derived_boundaries_and_lifecycle_controls(): void
    {
        [, $actor] = $this->contextWithAbilities('planning-year.view', 'planning-year.create');
        $this->actingAs($actor);

        $form = PlanningYearResource::form(Schema::make(app(CreatePlanningYearPage::class)));
        $this->assertSame(['year_label'], array_map(
            static fn ($component): string => $component->getName(),
            $form->getComponents(),
        ));

        $table = PlanningYearResource::table(Table::make(app(ListPlanningYears::class)));
        $this->assertSame(['year_label', 'start_date', 'end_date', 'active'], array_values(array_map(
            static fn ($column): string => $column->getName(),
            $table->getColumns(),
        )));
        $this->assertNotNull($table->getAction('deactivate'));
        $this->assertNotNull($table->getAction('reactivate'));
        $this->assertNull($table->getAction('edit'));
        $this->assertNull($table->getAction('delete'));
        $this->assertArrayNotHasKey('edit', PlanningYearResource::getPages());
        $this->assertArrayNotHasKey('view', PlanningYearResource::getPages());
    }

    public function test_create_page_and_lifecycle_table_actions_delegate_to_domain_actions_with_lock_versions(): void
    {
        [$tenant, $actor] = $this->contextWithAbilities(
            'planning-year.view',
            'planning-year.create',
            'planning-year.deactivate',
            'planning-year.reactivate',
        );
        $this->actingAs($actor);

        $created = $this->invokePageHandler(new CreatePlanningYearPage, 'handleRecordCreation', ['year_label' => 2026]);
        $this->assertInstanceOf(PlanningYear::class, $created);
        $this->assertSame($tenant->getKey(), $created->tenant_id);

        $table = PlanningYearResource::table(Table::make(app(ListPlanningYears::class)));
        $deactivate = $table->getAction('deactivate');
        $this->assertNotNull($deactivate);
        $deactivateHandler = $deactivate->getActionFunction();
        $this->assertNotNull($deactivateHandler);
        $deactivateHandler($created);
        $created->refresh();
        $this->assertFalse($created->active);
        $this->assertSame(2, $created->lock_version);

        $reactivate = $table->getAction('reactivate');
        $this->assertNotNull($reactivate);
        $reactivateHandler = $reactivate->getActionFunction();
        $this->assertNotNull($reactivateHandler);
        $reactivateHandler($created);
        $created->refresh();
        $this->assertTrue($created->active);
        $this->assertSame(3, $created->lock_version);
    }

    public function test_resource_ui_delegates_writes_and_contains_no_direct_eloquent_mutation(): void
    {
        $paths = [
            app_path('Filament/Resources/PlanningYears/PlanningYearResource.php'),
            app_path('Filament/Resources/PlanningYears/Pages/ListPlanningYears.php'),
            app_path('Filament/Resources/PlanningYears/Pages/CreatePlanningYear.php'),
        ];
        $source = '';

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $source .= $contents;
        }

        $this->assertStringContainsString('CreatePlanningYear', $source);
        $this->assertStringContainsString('DeactivatePlanningYear', $source);
        $this->assertStringContainsString('ReactivatePlanningYear', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:(?:PlanningYear)::(?:create|forceCreate|updateOrCreate|insert|upsert|destroy)|DB::(?:table|statement|insert|update|delete)|->(?:forceCreate|update|updateOrCreate|save|saveQuietly|delete|deleteQuietly|forceDelete|insert|upsert|forceFill))\s*\(/',
            $source,
        );
    }

    /** @return array{Tenant, User, TenantContext} */
    private function contextWithAbilities(string ...$abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $context = new TenantContext($tenant, $actor);
        $this->app->instance(TenantContext::class, $context);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        foreach ($abilities as $ability) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Planning resource '.$ability,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($ability);
            $actor->assignRole($role);
        }
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor, $context];
    }

    private function invokePageHandler(object $page, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($page, $method))->invoke($page, ...$arguments);
    }
}
