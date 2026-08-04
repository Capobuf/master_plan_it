<?php

namespace Tests\Livewire\Authorization;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Filament\Resources\Roles\Pages\CreateRole as CreateRolePage;
use App\Filament\Resources\Roles\Pages\EditRole as EditRolePage;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Panel::make()->id('role-resource-test'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_role_policy_maps_the_exact_manage_ability_and_complete_filament_method_set(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $sameTenantRole = $this->tenantRole($context->tenant, 'Same tenant role', ['dashboard.view']);
        $foreignRole = $this->tenantRole(Tenant::factory()->create(), 'Foreign role', ['dashboard.view']);
        $globalRole = Role::query()->whereNull('tenant_id')->where('name', 'Editor')->firstOrFail();
        $policy = app(RolePolicy::class);

        $this->assertAllowed($policy->viewAny($administrator));
        $this->assertAllowed($policy->view($administrator, $sameTenantRole));
        $this->assertAllowed($policy->create($administrator));
        $this->assertAllowed($policy->update($administrator, $sameTenantRole));
        $this->assertAllowed($policy->delete($administrator, $sameTenantRole));
        $this->assertDenied($policy->view($administrator, $foreignRole));
        $this->assertDenied($policy->update($administrator, $foreignRole));
        $this->assertDenied($policy->delete($administrator, $foreignRole));
        $this->assertDenied($policy->view($administrator, $globalRole));
        $this->assertDenied($policy->update($administrator, $globalRole));
        $this->assertDenied($policy->delete($administrator, $globalRole));
        $this->assertDenied($policy->deleteAny($administrator));
        $this->assertDenied($policy->forceDelete($administrator, $sameTenantRole));
        $this->assertDenied($policy->forceDeleteAny($administrator));
        $this->assertDenied($policy->restore($administrator, $sameTenantRole));
        $this->assertDenied($policy->restoreAny($administrator));
        $this->assertDenied($policy->replicate($administrator, $sameTenantRole));
        $this->assertDenied($policy->reorder($administrator));

        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->assertDenied($policy->viewAny($tenantUser));
        $this->assertDenied($policy->create($tenantUser));
        $this->assertDenied($policy->view($tenantUser, $sameTenantRole));
        $this->assertDenied($policy->update($tenantUser, $sameTenantRole));
        $this->assertDenied($policy->delete($tenantUser, $sameTenantRole));

        $administratorRole = Role::query()
            ->whereNull('tenant_id')
            ->where('name', 'Administrator')
            ->firstOrFail();
        $administratorRole->revokePermissionTo('platform.roles.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDenied($policy->viewAny($administrator));
        $this->assertDenied($policy->create($administrator));
        $this->assertDenied($policy->view($administrator, $sameTenantRole));
        $this->assertDenied($policy->update($administrator, $sameTenantRole));
        $this->assertDenied($policy->delete($administrator, $sameTenantRole));
    }

    public function test_administrator_can_manage_roles_in_an_inactive_selected_tenant(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $role = $this->tenantRole($context->tenant, 'Inactive tenant role', ['dashboard.view']);
        $policy = app(RolePolicy::class);

        $this->assertSame(TenantState::Inactive, $context->tenant->state);
        $this->assertAllowed($policy->viewAny($administrator));
        $this->assertAllowed($policy->create($administrator));
        $this->assertAllowed($policy->update($administrator, $role));
        $this->assertAllowed($policy->delete($administrator, $role));
    }

    public function test_resource_query_contains_only_roles_owned_by_the_selected_tenant(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $sameTenantRole = $this->tenantRole($context->tenant, 'Selected tenant role', ['dashboard.view']);
        $foreignRole = $this->tenantRole(Tenant::factory()->create(), 'Other tenant role', ['dashboard.view']);
        $globalIds = Role::query()->whereNull('tenant_id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->actingAs($administrator);

        $roleIds = RoleResource::getEloquentQuery()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $sameTenantRole->getKey()], $roleIds);
        $this->assertNotContains((int) $foreignRole->getKey(), $roleIds);
        foreach ($globalIds as $globalId) {
            $this->assertNotContains($globalId, $roleIds);
        }
    }

    public function test_role_form_options_come_only_from_the_code_owned_tenant_catalogue(): void
    {
        [$administrator] = $this->administratorContext();
        $this->actingAs($administrator);
        Permission::query()->firstOrCreate(['name' => 'persisted.rogue', 'guard_name' => 'web']);

        $schema = RoleResource::form(Schema::make(app(CreateRolePage::class)));
        $fields = $schema->getFlatComponents(withHidden: true, withAbsoluteKeys: true);
        $abilities = collect($fields)->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'abilities');

        $this->assertNotNull($abilities);
        $this->assertTrue(method_exists($abilities, 'getOptions'));
        $options = $abilities->getOptions();

        $this->assertSame(PermissionCatalogue::tenantAbilities(), array_keys($options));
        $this->assertArrayNotHasKey('persisted.rogue', $options);
        foreach (PermissionCatalogue::protectedAbilities() as $protectedAbility) {
            $this->assertArrayNotHasKey($protectedAbility, $options);
        }
        foreach ($options as $label) {
            $this->assertIsString($label);
            $this->assertNotSame('', trim($label));
        }
    }

    public function test_create_and_edit_pages_delegate_to_role_actions_and_reject_protected_submission(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $this->actingAs($administrator);

        $created = $this->invokePageHandler(new CreateRolePage, 'handleRecordCreation', [
            'name' => 'Resource role',
            'abilities' => ['dashboard.view', 'planning-year.view'],
        ]);

        $this->assertInstanceOf(Role::class, $created);
        $this->assertSame($context->tenantId, (int) $created->tenant_id);
        $this->assertSame(
            ['dashboard.view', 'planning-year.view'],
            $created->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.role.created',
            'subject_id' => $created->getKey(),
        ]);

        $updated = $this->invokePageHandler(new EditRolePage, 'handleRecordUpdate', $created, [
            'name' => 'Updated resource role',
            'abilities' => ['planning-year.create', 'planning-year.view'],
        ]);

        $this->assertInstanceOf(Role::class, $updated);
        $this->assertSame('Updated resource role', $updated->name);
        $this->assertSame(
            ['planning-year.create', 'planning-year.view'],
            $updated->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.role.updated',
            'subject_id' => $created->getKey(),
        ]);

        try {
            $this->invokePageHandler(new EditRolePage, 'handleRecordUpdate', $updated, [
                'name' => 'Unsafe resource role',
                'abilities' => ['platform.roles.manage'],
            ]);
            $this->fail('The RoleResource accepted a protected platform ability.');
        } catch (DomainException $exception) {
            $this->assertSame('PLATFORM_ABILITY_PROTECTED', $exception->getMessage());
        }

        $updated->refresh();
        $this->assertSame('Updated resource role', $updated->name);
        $this->assertSame(
            ['planning-year.create', 'planning-year.view'],
            $updated->permissions()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_duplicate_and_delete_table_actions_create_an_independent_tenant_copy_then_use_domain_deletion(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $source = $this->tenantRole($context->tenant, 'Editor', ['dashboard.view', 'planning-year.view']);
        $this->actingAs($administrator);
        $table = RoleResource::table(Table::make(app(ListRoles::class)));
        $duplicate = $table->getAction('duplicate');

        $this->assertNotNull($duplicate);
        $duplicateSchema = $duplicate->getSchema(Schema::make(app(ListRoles::class)));
        $this->assertNotNull($duplicateSchema);
        $this->assertContains('name', array_map(
            static fn ($component): string => $component->getName(),
            $duplicateSchema->getFlatComponents(withHidden: true),
        ));
        $duplicateHandler = $duplicate->getActionFunction();
        $this->assertNotNull($duplicateHandler);
        $duplicateHandler($source, ['name' => 'Editor copy']);

        $copy = Role::query()
            ->where('tenant_id', $context->tenantId)
            ->where('name', 'Editor copy')
            ->firstOrFail();
        $this->assertNotSame($source->getKey(), $copy->getKey());
        $this->assertSame($context->tenantId, (int) $copy->tenant_id);
        $this->assertSame(
            ['dashboard.view', 'planning-year.view'],
            $copy->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(
            ['dashboard.view', 'planning-year.view'],
            $source->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.role.created',
            'subject_id' => $copy->getKey(),
        ]);

        $delete = $table->getAction('delete');
        $this->assertNotNull($delete);
        $deleteHandler = $delete->getActionFunction();
        $this->assertNotNull($deleteHandler);
        $deleteHandler($copy);

        $this->assertDatabaseMissing('roles', ['id' => $copy->getKey()]);
        $this->assertDatabaseHas('roles', ['id' => $source->getKey(), 'tenant_id' => $context->tenantId]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.role.deleted',
            'subject_id' => $copy->getKey(),
        ]);
    }

    public function test_role_resource_ui_contains_no_direct_eloquent_write_path(): void
    {
        $paths = [
            app_path('Filament/Resources/Roles/RoleResource.php'),
            app_path('Filament/Resources/Roles/Pages/ListRoles.php'),
            app_path('Filament/Resources/Roles/Pages/CreateRole.php'),
            app_path('Filament/Resources/Roles/Pages/EditRole.php'),
            app_path('Filament/Resources/Roles/Schemas/RoleForm.php'),
            app_path('Filament/Resources/Roles/Tables/RolesTable.php'),
        ];
        $source = '';

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $source .= $contents;
        }

        $this->assertStringContainsString('CreateTenantRole', $source);
        $this->assertStringContainsString('UpdateTenantRole', $source);
        $this->assertStringContainsString('DeleteTenantRole', $source);
        $this->assertDoesNotMatchRegularExpression('/->(?:create|update|save|delete|syncPermissions)\s*\(/', $source);
    }

    /** @return array{User, TenantContext} */
    private function administratorContext(bool $inactiveTenant = false): array
    {
        $tenant = Tenant::factory()->create([
            'state' => $inactiveTenant ? TenantState::Inactive : TenantState::Active,
        ]);
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $context = new TenantContext($tenant, $administrator);
        $this->app->instance(TenantContext::class, $context);

        return [$administrator, $context];
    }

    /** @param list<string> $abilities */
    private function tenantRole(Tenant $tenant, string $name, array $abilities): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(Permission::query()->whereIn('name', $abilities)->get());

        return $role;
    }

    private function invokePageHandler(object $page, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($page, $method))->invoke($page, ...$arguments);
    }

    private function assertAllowed(bool|Response $result): void
    {
        $this->assertTrue($result instanceof Response ? $result->allowed() : $result);
    }

    private function assertDenied(bool|Response $result): void
    {
        $this->assertFalse($result instanceof Response ? $result->allowed() : $result);
    }
}
