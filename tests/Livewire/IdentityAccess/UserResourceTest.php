<?php

namespace Tests\Livewire\IdentityAccess;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Filament\Resources\Users\Pages\CreateUser as CreateUserPage;
use App\Filament\Resources\Users\Pages\EditUser as EditUserPage;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Filament::setCurrentPanel(Panel::make()->id('user-resource-test'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_user_policy_maps_the_exact_manage_ability_and_complete_filament_method_set(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $sameTenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $deactivatedSameTenantUser = User::factory()->inactive()->create(['tenant_id' => $context->tenantId]);
        $foreignUser = User::factory()->create(['tenant_id' => Tenant::factory()->create()->getKey()]);
        $globalUser = User::factory()->create(['tenant_id' => null]);
        $policy = app(UserPolicy::class);

        $this->assertAllowed($policy->viewAny($administrator));
        $this->assertAllowed($policy->view($administrator, $sameTenantUser));
        $this->assertAllowed($policy->create($administrator));
        $this->assertAllowed($policy->update($administrator, $sameTenantUser));
        $this->assertAllowed($policy->update($administrator, $deactivatedSameTenantUser));
        $this->assertAllowed($policy->deactivate($administrator, $sameTenantUser));
        $this->assertDenied($policy->deactivate($administrator, $deactivatedSameTenantUser));
        $this->assertDenied($policy->view($administrator, $foreignUser));
        $this->assertDenied($policy->update($administrator, $foreignUser));
        $this->assertDenied($policy->deactivate($administrator, $foreignUser));
        $this->assertDenied($policy->view($administrator, $globalUser));
        $this->assertDenied($policy->update($administrator, $globalUser));
        $this->assertDenied($policy->deactivate($administrator, $globalUser));
        $this->assertDenied($policy->delete($administrator, $sameTenantUser));
        $this->assertDenied($policy->deleteAny($administrator));
        $this->assertDenied($policy->forceDelete($administrator, $sameTenantUser));
        $this->assertDenied($policy->forceDeleteAny($administrator));
        $this->assertDenied($policy->restore($administrator, $sameTenantUser));
        $this->assertDenied($policy->restoreAny($administrator));
        $this->assertDenied($policy->replicate($administrator, $sameTenantUser));
        $this->assertDenied($policy->reorder($administrator));

        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->assertDenied($policy->viewAny($tenantUser));
        $this->assertDenied($policy->create($tenantUser));
        $this->assertDenied($policy->view($tenantUser, $sameTenantUser));
        $this->assertDenied($policy->update($tenantUser, $sameTenantUser));
        $this->assertDenied($policy->deactivate($tenantUser, $sameTenantUser));

        $administratorRole = Role::query()
            ->whereNull('tenant_id')
            ->where('name', 'Administrator')
            ->firstOrFail();
        $administratorRole->revokePermissionTo('platform.users.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDenied($policy->viewAny($administrator));
        $this->assertDenied($policy->create($administrator));
        $this->assertDenied($policy->view($administrator, $sameTenantUser));
        $this->assertDenied($policy->update($administrator, $sameTenantUser));
        $this->assertDenied($policy->deactivate($administrator, $sameTenantUser));
    }

    public function test_administrator_can_manage_users_in_an_inactive_selected_tenant(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $user = User::factory()->create(['tenant_id' => $context->tenantId]);
        $policy = app(UserPolicy::class);

        $this->assertSame(TenantState::Inactive, $context->tenant->state);
        $this->assertAllowed($policy->viewAny($administrator));
        $this->assertAllowed($policy->create($administrator));
        $this->assertAllowed($policy->update($administrator, $user));
        $this->assertAllowed($policy->deactivate($administrator, $user));
    }

    public function test_resource_query_contains_only_users_owned_by_the_selected_tenant(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $sameTenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $foreignUser = User::factory()->create(['tenant_id' => Tenant::factory()->create()->getKey()]);
        $globalUser = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($administrator);

        $userIds = UserResource::getEloquentQuery()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $sameTenantUser->getKey()], $userIds);
        $this->assertNotContains((int) $foreignUser->getKey(), $userIds);
        $this->assertNotContains((int) $globalUser->getKey(), $userIds);
        $this->assertNotContains((int) $administrator->getKey(), $userIds);
    }

    public function test_user_form_exposes_only_same_tenant_roles_and_requires_a_non_empty_complete_set(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $sameTenantRole = $this->tenantRole($context->tenant, 'Selected tenant role', ['dashboard.view']);
        $foreignRole = $this->tenantRole(Tenant::factory()->create(), 'Foreign role', ['dashboard.view']);
        $globalRole = Role::query()->whereNull('tenant_id')->where('name', 'Editor')->firstOrFail();
        $this->actingAs($administrator);

        $schema = UserResource::form(Schema::make(app(CreateUserPage::class)));
        $fields = $schema->getFlatComponents(withHidden: true, withAbsoluteKeys: true);
        $roles = collect($fields)->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'roles');

        $this->assertNotNull($roles);
        $this->assertTrue(method_exists($roles, 'getOptions'));
        $this->assertTrue(method_exists($roles, 'isMultiple'));
        $this->assertTrue($roles->isMultiple());
        $this->assertTrue($roles->isRequired());
        $options = $roles->getOptions();
        $roleIds = array_map('intval', array_keys($options));

        $this->assertSame([(int) $sameTenantRole->getKey()], $roleIds);
        $this->assertNotContains((int) $foreignRole->getKey(), $roleIds);
        $this->assertNotContains((int) $globalRole->getKey(), $roleIds);
        $this->assertSame('Selected tenant role', $options[$sameTenantRole->getKey()]);
    }

    public function test_create_and_edit_pages_delegate_identity_and_complete_role_writes_to_actions(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $viewer = $this->tenantRole($context->tenant, 'Resource viewer', ['dashboard.view']);
        $planner = $this->tenantRole($context->tenant, 'Resource planner', ['planning-year.create']);
        $this->actingAs($administrator);

        $created = $this->invokePageHandler(new CreateUserPage, 'handleRecordCreation', [
            'name' => 'Resource user',
            'email' => 'resource-user@example.test',
            'password' => 'temporary-password',
            'roles' => [$viewer->getKey()],
        ]);

        $this->assertInstanceOf(User::class, $created);
        $this->assertSame($context->tenantId, (int) $created->tenant_id);
        $this->assertTrue($created->is_active);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $viewer->getKey(),
            'model_id' => $created->getKey(),
            'model_type' => $created->getMorphClass(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.user.created',
            'subject_id' => $created->getKey(),
        ]);

        $updated = $this->invokePageHandler(new EditUserPage, 'handleRecordUpdate', $created, [
            'name' => 'Updated resource user',
            'email' => 'updated-resource-user@example.test',
            'roles' => [$viewer->getKey(), $planner->getKey()],
        ]);

        $this->assertInstanceOf(User::class, $updated);
        $this->assertSame('Updated resource user', $updated->name);
        $this->assertSame('updated-resource-user@example.test', $updated->email);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $viewer->getKey(),
            'model_id' => $updated->getKey(),
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $planner->getKey(),
            'model_id' => $updated->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.user.updated',
            'subject_id' => $updated->getKey(),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.user.roles-updated',
            'subject_id' => $updated->getKey(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($context->tenantId);
        $updated->unsetRelation('roles');
        $updated->unsetRelation('permissions');
        $this->assertTrue($updated->can('dashboard.view'));
        $this->assertTrue($updated->can('planning-year.create'));
    }

    public function test_empty_role_submission_is_rejected_before_create_or_update_side_effects(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Existing complete role', ['dashboard.view']);
        $target = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Unchanged user',
            'email' => 'unchanged-user@example.test',
        ]);
        $this->assignRoleDirectly($target, $context->tenantId, $role);
        $this->actingAs($administrator);

        try {
            $this->invokePageHandler(new CreateUserPage, 'handleRecordCreation', [
                'name' => 'Invalid resource user',
                'email' => 'invalid-resource-user@example.test',
                'password' => 'temporary-password',
                'roles' => [],
            ]);
            $this->fail('The UserResource created a tenant user without a role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }

        try {
            $this->invokePageHandler(new EditUserPage, 'handleRecordUpdate', $target, [
                'name' => 'Should not be persisted',
                'email' => 'should-not-persist@example.test',
                'roles' => [],
            ]);
            $this->fail('The UserResource removed the complete tenant role set.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }

        $target->refresh();
        $this->assertSame('Unchanged user', $target->name);
        $this->assertSame('unchanged-user@example.test', $target->email);
        $this->assertDatabaseMissing('users', ['email' => 'invalid-resource-user@example.test']);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $target->getKey(),
        ]);
    }

    public function test_deactivate_table_action_delegates_to_the_domain_action_without_deleting_identity(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $role = $this->tenantRole($context->tenant, 'Inactive tenant user role', ['dashboard.view']);
        $target = User::factory()->create(['tenant_id' => $context->tenantId, 'is_active' => true]);
        $this->assignRoleDirectly($target, $context->tenantId, $role);
        $passwordHash = $target->password;
        $this->actingAs($administrator);
        $table = UserResource::table(Table::make(app(ListUsers::class)));
        $deactivate = $table->getAction('deactivate');

        $this->assertNotNull($deactivate);
        $handler = $deactivate->getActionFunction();
        $this->assertNotNull($handler);
        $handler($target);

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertSame($passwordHash, $target->password);
        $this->assertDatabaseHas('users', [
            'id' => $target->getKey(),
            'tenant_id' => $context->tenantId,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.user.deactivated',
            'subject_id' => $target->getKey(),
        ]);
    }

    public function test_user_resource_ui_contains_no_direct_eloquent_or_permission_write_path(): void
    {
        $paths = [
            app_path('Filament/Resources/Users/UserResource.php'),
            app_path('Filament/Resources/Users/Pages/ListUsers.php'),
            app_path('Filament/Resources/Users/Pages/CreateUser.php'),
            app_path('Filament/Resources/Users/Pages/EditUser.php'),
            app_path('Filament/Resources/Users/Schemas/UserForm.php'),
            app_path('Filament/Resources/Users/Tables/UsersTable.php'),
        ];
        $source = '';

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $source .= $contents;
        }

        $this->assertStringContainsString('CreateTenantUser', $source);
        $this->assertStringContainsString('UpdateTenantUser', $source);
        $this->assertStringContainsString('AssignTenantRoles', $source);
        $this->assertStringContainsString('DeactivateTenantUser', $source);
        $this->assertDoesNotMatchRegularExpression('/->(?:create|update|save|delete|syncRoles|givePermissionTo)\s*\(/', $source);
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

    private function assignRoleDirectly(User $user, int $tenantId, Role $role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            $user->assignRole($role);
        } finally {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previous);
        }
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
