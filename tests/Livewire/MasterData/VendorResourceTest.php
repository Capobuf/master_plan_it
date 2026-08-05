<?php

namespace Tests\Livewire\MasterData;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Concerns\UsesTenantContextRoutes;
use App\Filament\Resources\CostCenters\CostCenterResource;
use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\VendorResource;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
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

class VendorResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Panel::make()->id('vendor-resource-test'));
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
        [$tenant, $actor, $context] = $this->context('vendor.view', 'vendor.create');
        $sameTenant = Vendor::factory()->for($tenant)->create(['name' => 'Local supplier']);
        $foreign = Vendor::factory()->for(Tenant::factory())->create(['name' => 'Foreign supplier']);
        $this->actingAs($actor);

        $this->assertContains(UsesTenantContextRoutes::class, (new ReflectionClass(VendorResource::class))->getTraitNames());
        $this->assertSame([
            ResolveTenantContext::class,
            SetPermissionTeamContext::class,
            EnsureTenantIsActive::class,
            ApplyTenantPresentationContext::class,
        ], VendorResource::getRouteMiddleware(Filament::getCurrentPanel()));
        $this->assertSame([(int) $sameTenant->getKey()], VendorResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains((int) $foreign->getKey(), VendorResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(VendorResource::canCreate());
        $this->assertSame($context->tenantId, VendorResource::tenantContext()->tenantId);
    }

    public function test_real_panel_registers_vendor_exactly_once_after_cost_center(): void
    {
        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());
        $resources = array_values($panel->getResources());
        $costCenterIndex = array_search(CostCenterResource::class, $resources, true);
        $vendorIndex = array_search(VendorResource::class, $resources, true);

        $this->assertSame(1, count(array_keys($resources, VendorResource::class, true)));
        $this->assertIsInt($costCenterIndex);
        $this->assertIsInt($vendorIndex);
        $this->assertGreaterThan($costCenterIndex, $vendorIndex);
    }

    public function test_real_admin_panel_shows_vendor_navigation_and_allows_direct_route_for_authorized_tenant_actor(): void
    {
        [, $actor] = $this->context('vendor.view');
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')->assertSuccessful()->assertSee('Vendors');
        $this->get('/admin/vendors')->assertSuccessful()->assertSee('Vendors');
    }

    public function test_real_admin_panel_hides_vendor_navigation_and_denies_direct_route_without_exact_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($actor);

        $this->get('/admin')->assertSuccessful()->assertDontSee('Vendors');
        $this->get('/admin/vendors')->assertForbidden();
    }

    public function test_tenantless_administrator_keeps_global_routes_but_is_forbidden_from_vendors(): void
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $this->app->forgetInstance(TenantContext::class);
        $this->actingAs($administrator);
        $this->app['session.store']->forget(EnterTenantContext::SESSION_KEY);

        $this->get('/admin')->assertSuccessful()->assertDontSee('Vendors');
        $this->get('/admin/tenants')->assertSuccessful();
        $this->get('/admin/vendors')->assertForbidden();
    }

    public function test_resource_exposes_contact_lifecycle_delete_and_revision_controls(): void
    {
        [$tenant, $actor] = $this->context(
            'vendor.view',
            'vendor.create',
            'vendor.update',
            'vendor.delete',
            'vendor.deactivate',
            'vendor.reactivate',
            'vendor.view-revisions',
        );
        $this->actingAs($actor);
        $form = VendorResource::form(Schema::make(app(CreateVendor::class)));
        $this->assertSame(['name', 'vat_number', 'email', 'phone', 'address'], array_map(static fn ($component): string => $component->getName(), $form->getComponents()));

        $table = VendorResource::table(Table::make(app(ListVendors::class)));
        $this->assertSame(['name', 'vat_number', 'email', 'active'], array_values(array_map(static fn ($column): string => $column->getName(), $table->getColumns())));
        foreach (['edit', 'deactivate', 'reactivate', 'delete', 'history'] as $action) {
            $this->assertNotNull($table->getAction($action));
        }
        $this->assertArrayHasKey('edit', VendorResource::getPages());
        $this->assertArrayHasKey('history', VendorResource::getPages());

        $eligible = Vendor::factory()->for($tenant)->create(['name' => 'Eligible delete']);
        $this->assertTrue(VendorResource::canDelete($eligible));
        $expense = Expense::factory()->for($tenant)->create();
        ExpenseRow::factory()->for($expense, 'expense')->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $eligible->getKey(),
        ]);
        $this->assertFalse(VendorResource::canDelete($eligible));
    }

    public function test_create_and_edit_pages_delegate_to_actions_without_direct_eloquent_writes(): void
    {
        [$tenant, $actor] = $this->context('vendor.view', 'vendor.create', 'vendor.update');
        $this->actingAs($actor);
        $created = $this->invoke(new CreateVendor, 'handleRecordCreation', [
            'name' => 'Resource supplier',
            'vat_number' => 'IT123',
            'email' => 'supplier@example.test',
            'phone' => null,
            'address' => null,
        ]);
        $this->assertInstanceOf(Vendor::class, $created);
        $this->assertSame($tenant->getKey(), $created->tenant_id);
        request()->attributes->remove(CorrelationId::class);
        $this->app->forgetInstance(CorrelationId::class);
        $updated = $this->invoke(new EditVendor, 'handleRecordUpdate', $created, [
            'name' => 'Updated supplier',
            'vat_number' => null,
            'email' => null,
            'phone' => '+39 02',
            'address' => 'Via Roma',
        ]);
        $this->assertSame('Updated supplier', $updated->name);

        $source = '';
        foreach ([
            app_path('Filament/Resources/Vendors/VendorResource.php'),
            app_path('Filament/Resources/Vendors/Pages/ListVendors.php'),
            app_path('Filament/Resources/Vendors/Pages/CreateVendor.php'),
            app_path('Filament/Resources/Vendors/Pages/EditVendor.php'),
            app_path('Filament/Resources/Vendors/Pages/VendorRevisionHistory.php'),
        ] as $path) {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $source .= $contents;
        }
        $this->assertStringContainsString('CreateVendor', $source);
        $this->assertStringContainsString('UpdateVendor', $source);
        $this->assertStringContainsString('DeleteVendor', $source);
        $this->assertStringContainsString('RestoreVendorRevision', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:(?:Vendor)::(?:create|forceCreate|updateOrCreate|insert|upsert|destroy)|DB::(?:table|statement|insert|update|delete)|->(?:forceCreate|update|updateOrCreate|save|saveQuietly|delete|deleteQuietly|forceDelete|insert|upsert|forceFill))\s*\(/',
            $source,
        );
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
                'name' => 'Vendor resource '.$ability,
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
}
