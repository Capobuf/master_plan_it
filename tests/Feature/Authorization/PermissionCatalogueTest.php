<?php

namespace Tests\Feature\Authorization;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionCatalogueSeeder;
use Database\Seeders\PlatformAdministratorSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCatalogueTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (['PLATFORM_ADMIN_NAME', 'PLATFORM_ADMIN_EMAIL', 'PLATFORM_ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }

    public function test_permission_teams_use_the_tenant_key_without_a_role_name_bypass(): void
    {
        $this->assertTrue(config('permission.teams'));
        $this->assertSame('tenant_id', config('permission.column_names.team_foreign_key'));
        $this->assertTrue(Schema::hasColumns('roles', ['id', 'tenant_id', 'name', 'guard_name']));
        $this->assertTrue(Schema::hasColumns('model_has_roles', ['role_id', 'tenant_id', 'model_id', 'model_type']));
        $this->assertTrue(Schema::hasColumns('model_has_permissions', ['permission_id', 'tenant_id', 'model_id', 'model_type']));
    }

    public function test_catalogue_and_seeded_roles_are_idempotent_and_keep_platform_abilities_global(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $this->assertSame(self::catalogue(), Permission::query()->orderBy('name')->pluck('name')->all());

        $administrator = Role::query()->where([
            'name' => 'Administrator',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();
        $editor = Role::query()->where([
            'name' => 'Editor',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();
        $viewer = Role::query()->where([
            'name' => 'Viewer',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();

        $this->assertSame(self::catalogue(), $administrator->permissions()->orderBy('name')->pluck('name')->all());
        $this->assertSame(self::editorAbilities(), $editor->permissions()->orderBy('name')->pluck('name')->all());
        $this->assertSame(self::viewerAbilities(), $viewer->permissions()->orderBy('name')->pluck('name')->all());
        $this->assertSame(3, Role::query()->whereNull('tenant_id')->count());

        foreach (self::protectedPlatformAbilities() as $ability) {
            $this->assertFalse($editor->hasPermissionTo($ability));
            $this->assertFalse($viewer->hasPermissionTo($ability));
        }
    }

    public function test_platform_administrator_assignment_and_ability_checks_are_isolated_to_reserved_scope_zero(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $boundary = app(PlatformAdministrator::class);
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId(41);
        $roles = collect();
        $permissions = collect();
        $administrator->setRelation('roles', $roles);
        $administrator->setRelation('permissions', $permissions);

        $boundary->assign($administrator);

        $role = Role::query()->where([
            'name' => 'Administrator',
            'guard_name' => 'web',
            'tenant_id' => null,
        ])->firstOrFail();

        $this->assertNull($administrator->tenant_id);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $administrator->getKey(),
            'model_type' => $administrator->getMorphClass(),
        ]);
        $this->assertTrue($boundary->hasProtectedRole($administrator));
        $this->assertTrue($boundary->allows($administrator, 'platform.tenants.view'));
        $this->assertTrue($boundary->allows($administrator, 'dashboard.view'));
        $this->assertFalse($boundary->allows($administrator, 'ability.not-in-catalogue'));
        $this->assertSame(41, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $administrator->getRelation('roles'));
        $this->assertSame($permissions, $administrator->getRelation('permissions'));
        $this->assertSame(0, Tenant::query()->whereKey(PlatformAdministrator::PLATFORM_TEAM_ID)->count());
    }

    public function test_platform_administrator_boundary_restores_null_and_tenant_team_contexts_exactly(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null]);
        $boundary = app(PlatformAdministrator::class);
        $registrar = app(PermissionRegistrar::class);
        $boundary->assign($administrator);

        foreach ([null, PlatformAdministrator::PLATFORM_TEAM_ID, 27] as $priorTeamId) {
            $registrar->setPermissionsTeamId($priorTeamId);
            $roles = collect();
            $permissions = collect();
            $administrator->setRelation('roles', $roles);
            $administrator->setRelation('permissions', $permissions);

            $this->assertTrue($boundary->allows($administrator, 'platform.settings.manage'));
            $this->assertSame($priorTeamId, $registrar->getPermissionsTeamId());
            $this->assertSame($roles, $administrator->getRelation('roles'));
            $this->assertSame($permissions, $administrator->getRelation('permissions'));
        }
    }

    public function test_platform_administrator_boundary_restores_context_when_assignment_fails(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(73);
        $roles = collect();
        $permissions = collect();
        $administrator->setRelation('roles', $roles);
        $administrator->setRelation('permissions', $permissions);

        Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->delete();

        try {
            app(PlatformAdministrator::class)->assign($administrator);
            $this->fail('Assignment succeeded without the protected global role.');
        } catch (ModelNotFoundException) {
            $this->assertSame(73, $registrar->getPermissionsTeamId());
            $this->assertSame($roles, $administrator->getRelation('roles'));
            $this->assertSame($permissions, $administrator->getRelation('permissions'));
        }
    }

    public function test_platform_administrator_boundary_rejects_tenant_and_inactive_users(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $inactiveTenantlessUser = User::factory()->inactive()->create(['tenant_id' => null]);
        $boundary = app(PlatformAdministrator::class);

        foreach ([$tenantUser, $inactiveTenantlessUser] as $user) {
            $this->assertFalse($boundary->hasProtectedRole($user));
            $this->assertFalse($boundary->allows($user, 'platform.tenants.view'));
        }

        $this->expectException(\DomainException::class);
        $boundary->assign($tenantUser);
    }

    public function test_platform_administrator_assignment_rejects_force_filled_tenantless_state_for_a_persisted_tenant_user(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $role = Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->firstOrFail();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(81);
        $roles = collect();
        $permissions = collect();
        $user->setRelation('roles', $roles);
        $user->setRelation('permissions', $permissions);
        $user->forceFill(['tenant_id' => null]);

        $rejected = false;

        try {
            app(PlatformAdministrator::class)->assign($user);
        } catch (\DomainException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame($tenant->getKey(), User::query()->findOrFail($user->getKey())->tenant_id);
        $this->assertSame(81, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $user->getRelation('roles'));
        $this->assertSame($permissions, $user->getRelation('permissions'));
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_assignment_rejects_force_filled_active_tenantless_state_for_a_persisted_inactive_tenant_user(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->inactive()->create(['tenant_id' => $tenant->getKey()]);
        $role = Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->firstOrFail();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(82);
        $roles = collect();
        $permissions = collect();
        $user->setRelation('roles', $roles);
        $user->setRelation('permissions', $permissions);
        $user->forceFill(['tenant_id' => null, 'is_active' => true]);

        $rejected = false;

        try {
            app(PlatformAdministrator::class)->assign($user);
        } catch (\DomainException) {
            $rejected = true;
        }

        $persistedUser = User::query()->findOrFail($user->getKey());
        $this->assertTrue($rejected);
        $this->assertSame($tenant->getKey(), $persistedUser->tenant_id);
        $this->assertFalse($persistedUser->is_active);
        $this->assertSame(82, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $user->getRelation('roles'));
        $this->assertSame($permissions, $user->getRelation('permissions'));
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_checks_reject_a_tenant_user_force_filled_with_an_administrator_primary_key(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $boundary = app(PlatformAdministrator::class);
        $boundary->assign($administrator);
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $tenantUserId = $tenantUser->getKey();
        $role = Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->firstOrFail();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(85);
        $roles = collect();
        $permissions = collect();
        $tenantUser->setRelation('roles', $roles);
        $tenantUser->setRelation('permissions', $permissions);
        $tenantUser->forceFill([$tenantUser->getKeyName() => $administrator->getKey()]);

        $this->assertSame($tenantUserId, $tenantUser->getRawOriginal($tenantUser->getKeyName()));
        $this->assertFalse($boundary->hasProtectedRole($tenantUser));
        $this->assertFalse($boundary->allows($tenantUser, 'platform.tenants.view'));
        $this->assertSame(85, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $tenantUser->getRelation('roles'));
        $this->assertSame($permissions, $tenantUser->getRelation('permissions'));
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $tenantUserId,
            'model_type' => $tenantUser->getMorphClass(),
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $administrator->getKey(),
            'model_type' => $tenantUser->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_assignment_rejects_a_tenant_user_force_filled_with_an_active_tenantless_victim_primary_key(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $tenantUserId = $tenantUser->getKey();
        $victim = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $role = Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->firstOrFail();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(86);
        $roles = collect();
        $permissions = collect();
        $tenantUser->setRelation('roles', $roles);
        $tenantUser->setRelation('permissions', $permissions);
        $tenantUser->forceFill([$tenantUser->getKeyName() => $victim->getKey()]);

        $rejected = false;

        try {
            app(PlatformAdministrator::class)->assign($tenantUser);
        } catch (\DomainException) {
            $rejected = true;
        }

        $this->assertTrue($rejected);
        $this->assertSame($tenantUserId, $tenantUser->getRawOriginal($tenantUser->getKeyName()));
        $this->assertSame(86, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $tenantUser->getRelation('roles'));
        $this->assertSame($permissions, $tenantUser->getRelation('permissions'));

        foreach ([$tenantUserId, $victim->getKey()] as $userId) {
            $this->assertDatabaseMissing('model_has_roles', [
                'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
                'role_id' => $role->getKey(),
                'model_id' => $userId,
                'model_type' => $tenantUser->getMorphClass(),
            ]);
        }
    }

    public function test_platform_administrator_checks_reject_a_persisted_administrator_deactivated_after_loading(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $boundary = app(PlatformAdministrator::class);
        $registrar = app(PermissionRegistrar::class);
        $boundary->assign($administrator);
        $role = Role::query()->where('name', 'Administrator')->whereNull('tenant_id')->firstOrFail();

        $registrar->setPermissionsTeamId(PlatformAdministrator::PLATFORM_TEAM_ID);
        $administrator->load('roles', 'permissions');
        $roles = $administrator->getRelation('roles');
        $permissions = $administrator->getRelation('permissions');
        $pivotCount = DB::table('model_has_roles')->where([
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $administrator->getKey(),
            'model_type' => $administrator->getMorphClass(),
        ])->count();

        User::query()->whereKey($administrator->getKey())->update(['is_active' => false]);
        $registrar->setPermissionsTeamId(83);

        $this->assertFalse($boundary->hasProtectedRole($administrator));
        $this->assertFalse($boundary->allows($administrator, 'platform.tenants.view'));
        $this->assertSame(83, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $administrator->getRelation('roles'));
        $this->assertSame($permissions, $administrator->getRelation('permissions'));
        $this->assertSame($pivotCount, DB::table('model_has_roles')->where([
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $role->getKey(),
            'model_id' => $administrator->getKey(),
            'model_type' => $administrator->getMorphClass(),
        ])->count());
    }

    public function test_platform_administrator_checks_fail_closed_for_a_user_missing_from_persistence(): void
    {
        $user = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(84);
        $roles = collect();
        $permissions = collect();
        $user->setRelation('roles', $roles);
        $user->setRelation('permissions', $permissions);
        User::query()->whereKey($user->getKey())->delete();

        $boundary = app(PlatformAdministrator::class);

        $this->assertFalse($boundary->hasProtectedRole($user));
        $this->assertFalse($boundary->allows($user, 'platform.tenants.view'));
        $this->assertSame(84, $registrar->getPermissionsTeamId());
        $this->assertSame($roles, $user->getRelation('roles'));
        $this->assertSame($permissions, $user->getRelation('permissions'));
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_seeder_fails_closed_when_any_credential_is_missing(): void
    {
        $cases = [
            'PLATFORM_ADMIN_NAME' => [null, 'platform-owner@example.test', 'secret'],
            'PLATFORM_ADMIN_EMAIL' => ['Platform Owner', null, 'secret'],
            'PLATFORM_ADMIN_PASSWORD' => ['Platform Owner', 'platform-owner@example.test', null],
        ];

        foreach ($cases as $missingKey => [$name, $email, $password]) {
            $this->setAdministratorEnvironment($name, $email, $password);
            $before = User::query()->count();

            try {
                Artisan::call('db:seed', ['--class' => PlatformAdministratorSeeder::class, '--force' => true]);
                $this->fail("Seeder accepted missing {$missingKey}.");
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString($missingKey, $exception->getMessage());
            }

            $this->assertSame($before, User::query()->count());
        }
    }

    public function test_root_database_seeder_never_creates_a_demo_account(): void
    {
        User::query()->where('email', 'test@example.com')->delete();

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_platform_administrator_seeder_rejects_a_tenantless_non_administrator_without_mutating_it(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $email = 'tenantless-collision-'.str()->uuid().'@example.test';
        $user = User::factory()->inactive()->create([
            'name' => 'Existing Tenantless User',
            'email' => $email,
            'tenant_id' => null,
            'password' => 'known-existing-secret',
        ]);
        $originalHash = $user->password;
        $this->setAdministratorEnvironment('Platform Owner', $email, 'installation-specific-secret');

        try {
            Artisan::call('db:seed', ['--class' => PlatformAdministratorSeeder::class, '--force' => true]);
            $this->fail('Seeder elevated a pre-existing tenantless non-Administrator.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }

        $user->refresh();
        $this->assertSame('Existing Tenantless User', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertSame($originalHash, $user->password);
        $this->assertTrue(Hash::check('known-existing-secret', $user->password));
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_seeder_rejects_a_tenant_user_collision_without_mutation(): void
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $tenant = Tenant::factory()->create();
        $email = 'tenant-collision-'.str()->uuid().'@example.test';
        $user = User::factory()->inactive()->create([
            'name' => 'Existing Tenant User',
            'email' => $email,
            'tenant_id' => $tenant->getKey(),
            'password' => 'known-tenant-secret',
        ]);
        $originalHash = $user->password;
        $this->setAdministratorEnvironment('Platform Owner', $email, 'installation-specific-secret');

        try {
            Artisan::call('db:seed', ['--class' => PlatformAdministratorSeeder::class, '--force' => true]);
            $this->fail('Seeder elevated a tenant user collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }

        $user->refresh();
        $this->assertSame('Existing Tenant User', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertSame($originalHash, $user->password);
        $this->assertSame($tenant->getKey(), $user->tenant_id);
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    public function test_platform_administrator_seeder_is_idempotent_and_never_creates_tenant_zero(): void
    {
        $email = 'platform-owner-'.str()->uuid().'@example.test';
        $password = 'installation-specific-secret';
        $this->setAdministratorEnvironment('Platform Owner', $email, $password);

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PlatformAdministratorSeeder::class, '--force' => true]);

        $administrator = User::query()->where('email', $email)->firstOrFail();
        $originalHash = $administrator->password;

        $this->setAdministratorEnvironment('Changed Platform Owner', $email, 'different-installation-secret');
        Artisan::call('db:seed', ['--class' => PlatformAdministratorSeeder::class, '--force' => true]);
        $administrator->refresh();

        $this->assertSame(1, User::query()->where('email', $email)->count());
        $this->assertNull($administrator->tenant_id);
        $this->assertTrue($administrator->is_active);
        $this->assertSame('Platform Owner', $administrator->name);
        $this->assertTrue(Hash::check($password, $administrator->password));
        $this->assertFalse(Hash::check('different-installation-secret', $administrator->password));
        $this->assertSame($originalHash, $administrator->password);
        $this->assertTrue(app(PlatformAdministrator::class)->hasProtectedRole($administrator));
        $this->assertSame(1, DB::table('model_has_roles')->where([
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'model_id' => $administrator->getKey(),
            'model_type' => $administrator->getMorphClass(),
        ])->count());
        $this->assertSame(0, Tenant::query()->whereKey(PlatformAdministrator::PLATFORM_TEAM_ID)->count());
    }

    private function setAdministratorEnvironment(?string $name, ?string $email, ?string $password): void
    {
        foreach (compact('name', 'email', 'password') as $key => $value) {
            $environmentKey = 'PLATFORM_ADMIN_'.strtoupper($key);

            if ($value === null) {
                putenv($environmentKey);
                unset($_ENV[$environmentKey], $_SERVER[$environmentKey]);

                continue;
            }

            putenv($environmentKey.'='.$value);
            $_ENV[$environmentKey] = $value;
            $_SERVER[$environmentKey] = $value;
        }
    }

    /** @return list<string> */
    private static function catalogue(): array
    {
        return self::sorted([
            ...self::protectedPlatformAbilities(),
            'dashboard.view', 'audit.view', 'notification.view',
            'planning-year.view', 'planning-year.create', 'planning-year.update', 'planning-year.deactivate', 'planning-year.reactivate',
            'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
            'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
            'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.print', 'expense.export',
            'attachment.view', 'attachment.upload', 'attachment.delete',
            'project.view', 'project.create', 'project.update', 'project.delete', 'project.view-revisions', 'project.restore-revision',
            'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.view-revisions', 'contract.restore-revision', 'contract.generate-occurrence', 'contract.suppress-generation', 'contract.resume-generation', 'contract.view-generation-history',
            'budget.view', 'budget.select-reference', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
            'budget-version.view', 'budget-version.create', 'budget-version.update-draft', 'budget-version.publish', 'budget-version.compare', 'budget-version.select-reference',
            'scenario.view', 'scenario.create', 'scenario.update', 'scenario.archive', 'scenario.delete', 'scenario.compare',
            'tenant-portability.export',
        ]);
    }

    /** @return list<string> */
    private static function protectedPlatformAbilities(): array
    {
        return self::sorted([
            'platform.tenants.view', 'platform.tenants.create', 'platform.tenants.update', 'platform.tenants.deactivate', 'platform.tenants.reactivate',
            'platform.users.manage', 'platform.roles.manage', 'platform.settings.manage', 'deletion-reason-setting.manage', 'platform.audit.view-global',
            'platform.migration.run', 'platform.portability.import', 'platform.backup.run', 'platform.restore.run', 'platform.deploy.view',
        ]);
    }

    /** @return list<string> */
    private static function editorAbilities(): array
    {
        return self::sorted([
            'dashboard.view', 'audit.view', 'notification.view', 'planning-year.view', 'planning-year.update',
            'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
            'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
            'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.print', 'expense.export',
            'attachment.view', 'attachment.upload', 'attachment.delete',
            'project.view', 'project.create', 'project.update', 'project.delete', 'project.view-revisions', 'project.restore-revision',
            'contract.view', 'contract.create', 'contract.update', 'contract.delete', 'contract.view-revisions', 'contract.restore-revision', 'contract.generate-occurrence', 'contract.suppress-generation', 'contract.resume-generation', 'contract.view-generation-history',
            'budget.view', 'budget.select-reference', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
            'budget-version.view', 'budget-version.create', 'budget-version.update-draft', 'budget-version.publish', 'budget-version.compare', 'budget-version.select-reference',
            'scenario.view', 'scenario.create', 'scenario.update', 'scenario.archive', 'scenario.delete', 'scenario.compare', 'tenant-portability.export',
        ]);
    }

    /** @return list<string> */
    private static function viewerAbilities(): array
    {
        return self::sorted([
            'dashboard.view', 'audit.view', 'notification.view', 'planning-year.view', 'cost-center.view', 'vendor.view', 'expense.view',
            'attachment.view', 'project.view', 'contract.view', 'budget.view', 'report.view', 'report.print', 'report.export-filtered', 'report.export-complete',
            'budget-version.view', 'budget-version.compare', 'scenario.view', 'scenario.compare', 'tenant-portability.export',
        ]);
    }

    /** @param list<string> $abilities
     * @return list<string>
     */
    private static function sorted(array $abilities): array
    {
        sort($abilities);

        return $abilities;
    }
}
