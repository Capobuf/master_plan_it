<?php

namespace Tests\Feature\IdentityAccess;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlatformIdentityIntegrationTest extends TestCase
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

    public function test_administrator_can_open_tenant_user_and_role_pages_while_tenant_users_are_denied(): void
    {
        [$administrator, $tenant] = $this->administratorAndTenant();
        $targetUser = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);
        $targetRole = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Inertia managed role',
            'guard_name' => 'web',
        ]);

        $this->actingAs($administrator)
            ->withSession([EnterTenantContext::SESSION_KEY => $tenant->getKey()])
            ->get('/operational/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Users/Index')
                ->where('abilities.create', true)
                ->where('abilities.resetPassword', true));

        $this->get('/operational/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Users/Create')
                ->has('roles', 1)
                ->where('roles.0.value', $targetRole->getKey())
                ->where('abilities.create', true)
                ->where('abilities.update', true));

        $this->get("/operational/users/{$targetUser->getKey()}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Users/Edit')
                ->where('user.id', $targetUser->getKey())
                ->where('abilities.deactivate', true)
                ->where('abilities.resetPassword', true));

        $this->get('/operational/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Roles/Index')
                ->where('abilities.create', true)
                ->where('abilities.delete', true));

        $this->get('/operational/roles/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Roles/Create')
                ->where('crudAbilities.create', true)
                ->where('crudAbilities.update', true));

        $this->get("/operational/roles/{$targetRole->getKey()}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Operational/Roles/Edit')
                ->where('role.id', $targetRole->getKey())
                ->where('crudAbilities.delete', true));

        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);

        $this->actingAs($tenantUser)
            ->get('/operational/users')
            ->assertForbidden();
        $this->get('/operational/roles')->assertForbidden();
    }

    public function test_identity_submit_routes_delegate_user_and_role_changes_to_domain_actions(): void
    {
        [$administrator, $tenant] = $this->administratorAndTenant();
        $this->actingAs($administrator)
            ->withSession([EnterTenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('operational.roles.store'), [
            'name' => 'Expense reader',
            'abilities' => ['dashboard.view', 'expense.view'],
        ])->assertRedirect(route('operational.roles.index'));

        $role = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Expense reader')
            ->firstOrFail();

        $this->put(route('operational.roles.update', $role->getKey()), [
            'name' => 'Expense viewer',
            'abilities' => ['dashboard.view', 'expense.view'],
        ])->assertRedirect(route('operational.roles.edit', $role->getKey()));

        $this->post(route('operational.users.store'), [
            'name' => 'Managed user',
            'email' => 'managed-user@example.test',
            'password' => 'temporary-password',
            'roles' => [$role->getKey()],
        ])->assertRedirect(route('operational.users.index'));

        $target = User::query()->where('email', 'managed-user@example.test')->firstOrFail();

        $this->put(route('operational.users.update', $target->getKey()), [
            'name' => 'Updated managed user',
            'email' => 'updated-managed-user@example.test',
            'roles' => [$role->getKey()],
        ])->assertRedirect(route('operational.users.edit', $target->getKey()));

        $this->post(route('operational.users.deactivate', $target->getKey()))
            ->assertRedirect(route('operational.users.index'));

        $target->refresh();
        $this->assertSame('Updated managed user', $target->name);
        $this->assertFalse($target->is_active);

        $deletable = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Deletable role',
            'guard_name' => 'web',
        ]);
        $this->delete(route('operational.roles.destroy', $deletable->getKey()))
            ->assertRedirect(route('operational.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $deletable->getKey()]);
    }

    public function test_protected_identity_abilities_are_omitted_from_tenant_roles_and_denied_to_a_tenant_user(): void
    {
        [$administrator, $tenant] = $this->administratorAndTenant();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Tenant identity attempt',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $tenantUser->assignRole($role);

        $this->assertContains('platform.users.manage', PermissionCatalogue::protectedAbilities());
        $this->assertContains('platform.roles.manage', PermissionCatalogue::protectedAbilities());
        $this->assertNotContains('platform.users.manage', PermissionCatalogue::tenantAbilities());
        $this->assertNotContains('platform.roles.manage', PermissionCatalogue::tenantAbilities());
        $this->assertFalse($tenantUser->can('platform.users.manage'));
        $this->assertFalse($tenantUser->can('platform.roles.manage'));

        $administrator->refresh();
        $this->assertTrue(app(PlatformAdministrator::class)->allows($administrator, 'platform.users.manage'));
        $this->assertTrue(app(PlatformAdministrator::class)->allows($administrator, 'platform.roles.manage'));
    }

    public function test_password_reset_endpoint_requires_confirmation_then_delegates_to_the_domain_action(): void
    {
        [$administrator, $tenant] = $this->administratorAndTenant();
        $target = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('original-password'),
        ]);
        $originalHash = $target->password;
        DB::table('sessions')->insert([
            'id' => 'inertia-password-reset-session',
            'user_id' => $target->getKey(),
            'ip_address' => '192.0.2.30',
            'user_agent' => 'inertia-password-reset-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->withSession([EnterTenantContext::SESSION_KEY => $tenant->getKey()])
            ->from(route('operational.users.edit', $target->getKey()))
            ->put(route('operational.users.password.update', $target->getKey()), [
                'password' => 'replacement-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors(['password']);

        $this->assertSame($originalHash, $target->fresh()?->password);
        $this->assertDatabaseHas('sessions', ['id' => 'inertia-password-reset-session']);

        $this->put(route('operational.users.password.update', $target->getKey()), [
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertRedirect(route('operational.users.edit', $target->getKey()));

        $this->assertTrue(Hash::check('replacement-password', (string) $target->fresh()?->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'inertia-password-reset-session']);
    }

    /** @return array{User, Tenant} */
    private function administratorAndTenant(): array
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return [$administrator, Tenant::factory()->create()];
    }
}
