<?php

namespace Tests\Feature\IdentityAccess;

use App\Domain\IdentityAccess\Actions\ResetTenantUserPassword;
use App\Domain\Tenancy\Data\TenantContext;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Actions\ResetTenantUserPasswordAction;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Database\Seeders\PermissionCatalogueSeeder;
use Filament\Panel;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Expectation;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlatformIdentityIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
        Mockery::close();

        parent::tearDown();
    }

    public function test_administrator_registers_one_existing_user_and_role_resource_and_only_administrator_can_access_them(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->actingAs($administrator);

        $panel = (new AdminPanelProvider($this->app))->panel(Panel::make());
        $resources = $panel->getResources();

        $this->assertSame(1, count(array_keys($resources, UserResource::class, true)), 'T001-014 must register the existing UserResource exactly once.');
        $this->assertSame(1, count(array_keys($resources, RoleResource::class, true)), 'T001-014 must register the existing RoleResource exactly once.');
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(RoleResource::canViewAny());

        $this->app->instance(TenantContext::class, new TenantContext($context->tenant, $tenantUser));
        $this->actingAs($tenantUser);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
    }

    public function test_protected_identity_abilities_are_omitted_from_tenant_roles_and_denied_to_a_tenant_user(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $role = Role::query()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Tenant identity attempt',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($context->tenantId);
        $tenantUser->assignRole($role);
        $this->app->instance(TenantContext::class, new TenantContext($context->tenant, $tenantUser));
        $this->actingAs($tenantUser);

        $this->assertContains('platform.users.manage', PermissionCatalogue::protectedAbilities());
        $this->assertContains('platform.roles.manage', PermissionCatalogue::protectedAbilities());
        $this->assertNotContains('platform.users.manage', PermissionCatalogue::tenantAbilities());
        $this->assertNotContains('platform.roles.manage', PermissionCatalogue::tenantAbilities());
        $this->assertFalse($tenantUser->can('platform.users.manage'));
        $this->assertFalse($tenantUser->can('platform.roles.manage'));
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());

        $administrator->refresh();
        $this->assertTrue(app(PlatformAdministrator::class)->allows($administrator, 'platform.users.manage'));
        $this->assertTrue(app(PlatformAdministrator::class)->allows($administrator, 'platform.roles.manage'));
    }

    public function test_users_table_exposes_an_executable_reset_action_that_delegates_once_to_the_domain_action(): void
    {
        $this->assertTrue(
            class_exists(ResetTenantUserPassword::class),
            'T001-013 must provide ResetTenantUserPassword before runtime UI delegation can execute.',
        );
        $this->assertTrue(
            class_exists(ResetTenantUserPasswordAction::class),
            'T001-014 must provide the exact ResetTenantUserPasswordAction adapter.',
        );
        $tableSource = file_get_contents(app_path('Filament/Resources/Users/Tables/UsersTable.php'));
        $adapterPath = (new \ReflectionClass(ResetTenantUserPasswordAction::class))->getFileName();
        $this->assertIsString($tableSource);
        $this->assertIsString($adapterPath);
        $adapterSource = file_get_contents($adapterPath);
        $this->assertIsString($adapterSource);
        $this->assertStringContainsString('ResetTenantUserPasswordAction::make()', $tableSource);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:(?:User|AuditEvent)::(?:create|update|delete)|DB::(?:table|statement|insert|update|delete)|->(?:save|saveQuietly|update|delete|forceFill))\s*\(/',
            $tableSource.$adapterSource,
        );

        [$administrator, $context] = $this->administratorContext();
        $target = User::factory()->create(['tenant_id' => $context->tenantId]);
        DB::table('sessions')->insert([
            'id' => 'runtime-table-action-session',
            'user_id' => $target->getKey(),
            'ip_address' => '192.0.2.30',
            'user_agent' => 'runtime-table-action-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
        $this->actingAs($administrator);
        $correlationId = app(CorrelationId::class)->value();
        $secret = 'runtime-table-action-secret';
        $targetBeforeHandler = (array) DB::table('users')->where('id', $target->getKey())->first();
        $auditCount = DB::table('audit_events')->count();
        $domainAction = Mockery::spy(app(ResetTenantUserPassword::class));
        $domainExpectation = $domainAction->shouldReceive('execute');
        $this->assertInstanceOf(Expectation::class, $domainExpectation);
        $domainExpectation
            ->withArgs(fn (User $actor, TenantContext $selectedContext, User $record, string $submittedSecret, string $submittedCorrelationId): bool => $actor->is($administrator)
                && $selectedContext === $context
                && $record->is($target)
                && $submittedSecret === $secret
                && $submittedCorrelationId === $correlationId)
            ->once()
            ->andReturn($target);
        $this->app->instance(ResetTenantUserPassword::class, $domainAction);

        $table = UserResource::table(Table::make(app(ListUsers::class)));
        $action = $table->getAction('resetPassword');

        $this->assertNotNull($action, 'UsersTable must wire the reset Action at runtime.');
        $handler = $action->getActionFunction();
        $this->assertNotNull($handler);

        $handler($target, ['password' => $secret]);

        $this->assertSame($targetBeforeHandler, (array) DB::table('users')->where('id', $target->getKey())->first());
        $this->assertDatabaseHas('sessions', [
            'id' => 'runtime-table-action-session',
            'user_id' => $target->getKey(),
        ]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    /** @return array{User, TenantContext} */
    private function administratorContext(): array
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create();
        $context = new TenantContext($tenant, $administrator);
        $this->app->instance(TenantContext::class, $context);

        return [$administrator, $context];
    }
}
