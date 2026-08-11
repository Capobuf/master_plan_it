<?php

namespace Tests\Feature\Authorization;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Domain\IdentityAccess\Actions\CreateTenantUser;
use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Domain\IdentityAccess\Actions\DeleteTenantRole;
use App\Domain\IdentityAccess\Actions\UpdateTenantRole;
use App\Domain\IdentityAccess\Actions\UpdateTenantUser;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProtectedPermissionTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private const PROTECTED_ABILITIES = [
        'platform.tenants.view',
        'platform.tenants.create',
        'platform.tenants.update',
        'platform.tenants.deactivate',
        'platform.tenants.reactivate',
        'platform.users.manage',
        'platform.roles.manage',
        'platform.settings.manage',
        'deletion-reason-setting.manage',
        'platform.audit.view-global',
        'platform.migration.run',
        'platform.portability.import',
        'platform.backup.run',
        'platform.restore.run',
        'platform.deploy.view',
    ];

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

        parent::tearDown();
    }

    public function test_tenant_role_create_and_update_reject_every_protected_platform_ability(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $registrar = app(PermissionRegistrar::class);
        $role = Role::query()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Safe Existing Role',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view']);

        foreach (self::PROTECTED_ABILITIES as $index => $protectedAbility) {
            $roleName = 'Protected Attempt '.str()->uuid();

            $registrar->setPermissionsTeamId(900 + $index);
            $this->assertDomainFailure(
                fn () => app(CreateTenantRole::class)->execute(
                    $administrator,
                    $context,
                    $roleName,
                    ['dashboard.view', $protectedAbility],
                    (string) str()->uuid(),
                ),
                'PLATFORM_ABILITY_PROTECTED',
            );
            $this->assertSame(900 + $index, $registrar->getPermissionsTeamId());
            $this->assertDatabaseMissing('roles', [
                'tenant_id' => $context->tenantId,
                'name' => $roleName,
            ]);

            $registrar->setPermissionsTeamId(950 + $index);
            $this->assertDomainFailure(
                fn () => app(UpdateTenantRole::class)->execute(
                    $administrator,
                    $context,
                    $role,
                    'Unsafe Existing Role',
                    ['dashboard.view', $protectedAbility],
                    (string) str()->uuid(),
                ),
                'PLATFORM_ABILITY_PROTECTED',
            );
            $this->assertSame(950 + $index, $registrar->getPermissionsTeamId());
        }

        $this->assertDatabaseHas('roles', ['id' => $role->getKey(), 'name' => 'Safe Existing Role']);
        $this->assertSame(
            ['dashboard.view'],
            Role::query()->findOrFail($role->getKey())->permissions()->pluck('name')->all(),
        );
    }

    public function test_global_source_roles_cannot_be_assigned_and_administrator_cannot_be_updated_or_deleted(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $administratorRole = Role::query()
            ->where('name', 'Administrator')
            ->where('guard_name', 'web')
            ->whereNull('tenant_id')
            ->firstOrFail();
        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $originalAbilities = $administratorRole->permissions()->orderBy('name')->pluck('name')->all();

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(990);
        $this->assertDomainFailure(
            fn () => app(UpdateTenantRole::class)->execute(
                $administrator,
                $context,
                $administratorRole,
                'Administrator Renamed',
                ['dashboard.view'],
                (string) str()->uuid(),
            ),
            'PLATFORM_ABILITY_PROTECTED',
        );
        $this->assertSame(990, $registrar->getPermissionsTeamId());
        $registrar->setPermissionsTeamId(991);
        $this->assertDomainFailure(
            fn () => app(DeleteTenantRole::class)->execute(
                $administrator,
                $context,
                $administratorRole,
                (string) str()->uuid(),
            ),
            'PLATFORM_ABILITY_PROTECTED',
        );
        $this->assertSame(991, $registrar->getPermissionsTeamId());
        foreach (['Administrator', 'Editor', 'Viewer'] as $index => $sourceRoleName) {
            $sourceRole = Role::query()->whereNull('tenant_id')->where('name', $sourceRoleName)->firstOrFail();
            $sentinel = 1000 + $index;
            app(PermissionRegistrar::class)->setPermissionsTeamId($sentinel);
            $this->assertDomainFailure(
                fn () => app(AssignTenantRoles::class)->execute(
                    $administrator,
                    $context,
                    $tenantUser,
                    [$sourceRole],
                    (string) str()->uuid(),
                ),
                'PLATFORM_ABILITY_PROTECTED',
            );
            $this->assertSame($sentinel, app(PermissionRegistrar::class)->getPermissionsTeamId());
            $this->assertDatabaseMissing('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $sourceRole->getKey(),
                'model_id' => $tenantUser->getKey(),
            ]);

            $createEmail = 'source-role-'.str()->uuid().'@example.test';
            $registrar->setPermissionsTeamId(1010 + $index);
            $this->assertDomainFailure(
                fn () => app(CreateTenantUser::class)->execute(
                    $administrator,
                    $context,
                    'Source Role User',
                    $createEmail,
                    'temporary-password',
                    [$sourceRole],
                    (string) str()->uuid(),
                ),
                'PLATFORM_ABILITY_PROTECTED',
            );
            $this->assertSame(1010 + $index, $registrar->getPermissionsTeamId());
            $this->assertDatabaseMissing('users', ['email' => $createEmail]);
        }

        $this->assertDatabaseHas('roles', [
            'id' => $administratorRole->getKey(),
            'tenant_id' => null,
            'name' => 'Administrator',
        ]);
        $this->assertSame(
            $originalAbilities,
            $administratorRole->refresh()->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $administratorRole->getKey(),
            'model_id' => $tenantUser->getKey(),
        ]);
    }

    public function test_role_assignment_never_creates_a_direct_user_permission(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = Role::query()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Role Only',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view', 'planning-year.view']);
        $tenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);

        app(AssignTenantRoles::class)->execute(
            $administrator,
            $context,
            $tenantUser,
            [$role],
            (string) str()->uuid(),
        );

        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $tenantUser->getKey(),
            'model_type' => $tenantUser->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('model_has_permissions', [
            'tenant_id' => $context->tenantId,
            'model_id' => $tenantUser->getKey(),
            'model_type' => $tenantUser->getMorphClass(),
        ]);
        $this->assertDatabaseCount('model_has_permissions', 0);
    }

    public function test_tenant_settings_abilities_are_assignable_and_are_not_protected_platform_abilities(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $name = 'Tenant settings '.str()->uuid();

        $role = app(CreateTenantRole::class)->execute(
            $administrator,
            $context,
            $name,
            ['tenant-settings.view', 'tenant-settings.update'],
            (string) str()->uuid(),
        );

        $this->assertSame(
            ['tenant-settings.update', 'tenant-settings.view'],
            $role->permissions()->orderBy('name')->pluck('name')->all(),
        );
        $this->assertNotContains('tenant-settings.view', self::PROTECTED_ABILITIES);
        $this->assertNotContains('tenant-settings.update', self::PROTECTED_ABILITIES);
    }

    public function test_each_action_requires_its_exact_platform_management_ability_and_restores_team_scope(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $administratorRole = Role::query()->whereNull('tenant_id')->where('name', 'Administrator')->firstOrFail();
        $registrar = app(PermissionRegistrar::class);
        $operations = $this->actionOperations($administrator, $context);

        foreach (['platform.roles.manage', 'platform.users.manage'] as $ability) {
            $administratorRole->revokePermissionTo($ability);
            $registrar->forgetCachedPermissions();

            foreach ($operations as $index => $operation) {
                if ($operation['ability'] !== $ability) {
                    continue;
                }

                $sentinel = 1100 + $index;
                $registrar->setPermissionsTeamId($sentinel);
                $this->assertAuthorizationFailure($operation['invoke'], 'PERMISSION_DENIED');
                $this->assertSame($sentinel, $registrar->getPermissionsTeamId(), $operation['name']);
            }

            $administratorRole->givePermissionTo($ability);
            $registrar->forgetCachedPermissions();
        }

        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $context->tenantId]);
    }

    public function test_each_action_rejects_actor_context_identity_mismatch_and_restores_team_scope(): void
    {
        [$administrator, $baseContext] = $this->administratorContext();
        $differentAdministrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($differentAdministrator);
        $mismatchedContext = new TenantContext($baseContext->tenant, $differentAdministrator);
        $registrar = app(PermissionRegistrar::class);

        foreach ($this->actionOperations($administrator, $mismatchedContext) as $index => $operation) {
            $sentinel = 1200 + $index;
            $registrar->setPermissionsTeamId($sentinel);
            $this->assertAuthorizationFailure($operation['invoke'], 'PERMISSION_DENIED');
            $this->assertSame($sentinel, $registrar->getPermissionsTeamId(), $operation['name']);
        }

        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $baseContext->tenantId]);
    }

    public function test_each_action_rejects_an_inactive_actor_and_restores_team_scope(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $administrator->forceFill(['is_active' => false])->save();
        $context = new TenantContext($context->tenant, $administrator->refresh());
        $registrar = app(PermissionRegistrar::class);

        foreach ($this->actionOperations($administrator, $context) as $index => $operation) {
            $sentinel = 1300 + $index;
            $registrar->setPermissionsTeamId($sentinel);
            $this->assertAuthorizationFailure($operation['invoke'], 'PERMISSION_DENIED');
            $this->assertSame($sentinel, $registrar->getPermissionsTeamId(), $operation['name']);
        }

        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $context->tenantId]);
    }

    public function test_each_action_rejects_an_in_memory_tenantless_actor_spoof_against_persisted_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $spoofedActor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $administratorRole = Role::query()->whereNull('tenant_id')->where('name', 'Administrator')->firstOrFail();

        // Simulate a corrupt legacy pivot without using the protected assignment boundary.
        DB::table('model_has_roles')->insert([
            'tenant_id' => PlatformAdministrator::PLATFORM_TEAM_ID,
            'role_id' => $administratorRole->getKey(),
            'model_id' => $spoofedActor->getKey(),
            'model_type' => $spoofedActor->getMorphClass(),
        ]);
        $spoofedActor->forceFill(['tenant_id' => null]);
        $context = new TenantContext($tenant, $spoofedActor);
        $registrar = app(PermissionRegistrar::class);

        foreach ($this->actionOperations($spoofedActor, $context) as $index => $operation) {
            $sentinel = 1350 + $index;
            $registrar->setPermissionsTeamId($sentinel);
            $this->assertAuthorizationFailure($operation['invoke'], 'PERMISSION_DENIED');
            $this->assertSame($sentinel, $registrar->getPermissionsTeamId(), $operation['name']);
        }

        $this->assertSame($tenant->getKey(), User::query()->findOrFail($spoofedActor->getKey())->tenant_id);
        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $tenant->getKey()]);
    }

    public function test_each_action_rejects_spoofed_or_nonpersisted_context_tenants_without_mutation(): void
    {
        [$administrator, $validContext] = $this->administratorContext();
        $targetTenant = $validContext->tenant;
        $persistedSourceTenant = Tenant::factory()->create();
        $persistedSourceTenant->forceFill([
            $persistedSourceTenant->getKeyName() => $targetTenant->getKey(),
        ]);
        $nonpersistedTenant = new Tenant;
        $nonpersistedTenant->forceFill($targetTenant->getAttributes());
        $registrar = app(PermissionRegistrar::class);

        $this->assertTrue($persistedSourceTenant->exists);
        $this->assertNotSame(
            $persistedSourceTenant->getRawOriginal($persistedSourceTenant->getKeyName()),
            $persistedSourceTenant->getKey(),
        );
        $this->assertFalse($nonpersistedTenant->exists);
        $this->assertSame($targetTenant->getKey(), $nonpersistedTenant->getKey());
        $involvedTenantIds = [
            (int) $targetTenant->getKey(),
            (int) $persistedSourceTenant->getRawOriginal($persistedSourceTenant->getKeyName()),
        ];

        $invalidContexts = [
            new TenantContext($persistedSourceTenant, $administrator),
            new TenantContext($nonpersistedTenant, $administrator),
        ];

        foreach ($invalidContexts as $contextIndex => $invalidContext) {
            $operations = $this->actionOperations($administrator, $invalidContext, $targetTenant);

            foreach ($operations as $operationIndex => $operation) {
                $before = $this->tenantMutationStates($involvedTenantIds);
                $sentinel = 1370 + ($contextIndex * 10) + $operationIndex;
                $registrar->setPermissionsTeamId($sentinel);
                $this->assertAuthorizationFailure($operation['invoke'], 'TENANT_CONTEXT_REQUIRED');
                $this->assertSame($sentinel, $registrar->getPermissionsTeamId(), $operation['name']);
                $this->assertSame(
                    $before,
                    $this->tenantMutationStates($involvedTenantIds),
                    $operation['name'].' mutated state for an invalid context tenant.',
                );
            }
        }

        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $targetTenant->getKey()]);
        $this->assertDatabaseMissing('audit_events', [
            'tenant_id' => $persistedSourceTenant->getRawOriginal($persistedSourceTenant->getKeyName()),
        ]);
    }

    public function test_tenant_users_cannot_manage_roles_even_when_a_role_name_suggests_privilege(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $context = new TenantContext($tenant, $tenantUser);
        $suggestiveRoleName = 'Administrator Assistant';

        $this->assertAuthorizationFailure(
            fn () => app(CreateTenantRole::class)->execute(
                $tenantUser,
                $context,
                $suggestiveRoleName,
                ['dashboard.view'],
                (string) str()->uuid(),
            ),
            'PERMISSION_DENIED',
        );

        $this->assertDatabaseMissing('roles', [
            'tenant_id' => $tenant->getKey(),
            'name' => $suggestiveRoleName,
        ]);
    }

    /** @return array{User, TenantContext} */
    private function administratorContext(): array
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create();

        return [$administrator, new TenantContext($tenant, $administrator)];
    }

    /**
     * @return list<array{name: string, ability: string, invoke: callable(): mixed}>
     */
    private function actionOperations(User $actor, TenantContext $context, ?Tenant $targetTenant = null): array
    {
        $targetTenant ??= $context->tenant;
        $targetTenantId = (int) $targetTenant->getKey();
        $fixtureId = (string) str()->uuid();
        $roleToUpdate = $this->tenantRole($targetTenant, 'Authorization Update Target '.$fixtureId);
        $roleToDelete = $this->tenantRole($targetTenant, 'Authorization Delete Target '.$fixtureId);
        $roleToAssign = $this->tenantRole($targetTenant, 'Authorization Assignment Target '.$fixtureId);
        $assignmentUser = User::factory()->create(['tenant_id' => $targetTenantId]);
        $updateUser = User::factory()->create(['tenant_id' => $targetTenantId]);
        $deactivateUser = User::factory()->create(['tenant_id' => $targetTenantId]);

        return [
            ['name' => 'CreateTenantRole', 'ability' => 'platform.roles.manage', 'invoke' => fn () => app(CreateTenantRole::class)->execute($actor, $context, 'Authorization Create '.str()->uuid(), ['dashboard.view'], (string) str()->uuid())],
            ['name' => 'UpdateTenantRole', 'ability' => 'platform.roles.manage', 'invoke' => fn () => app(UpdateTenantRole::class)->execute($actor, $context, $roleToUpdate, 'Authorization Updated', ['planning-year.view'], (string) str()->uuid())],
            ['name' => 'DeleteTenantRole', 'ability' => 'platform.roles.manage', 'invoke' => fn () => app(DeleteTenantRole::class)->execute($actor, $context, $roleToDelete, (string) str()->uuid())],
            ['name' => 'AssignTenantRoles', 'ability' => 'platform.users.manage', 'invoke' => fn () => app(AssignTenantRoles::class)->execute($actor, $context, $assignmentUser, [$roleToAssign], (string) str()->uuid())],
            ['name' => 'CreateTenantUser', 'ability' => 'platform.users.manage', 'invoke' => fn () => app(CreateTenantUser::class)->execute($actor, $context, 'Authorization Create User', 'create-'.str()->uuid().'@example.test', 'temporary-password', [$roleToAssign], (string) str()->uuid())],
            ['name' => 'UpdateTenantUser', 'ability' => 'platform.users.manage', 'invoke' => fn () => app(UpdateTenantUser::class)->execute($actor, $context, $updateUser, 'Authorization Updated User', 'update-'.str()->uuid().'@example.test', (string) str()->uuid())],
            ['name' => 'DeactivateTenantUser', 'ability' => 'platform.users.manage', 'invoke' => fn () => app(DeactivateTenantUser::class)->execute($actor, $context, $deactivateUser, [9, 3], (string) str()->uuid())],
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function tenantMutationState(int $tenantId): array
    {
        $roleIds = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->pluck('id');

        return [
            'roles' => DB::table('roles')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
            'role_has_permissions' => DB::table('role_has_permissions')
                ->whereIn('role_id', $roleIds)
                ->orderBy('role_id')
                ->orderBy('permission_id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
            'users' => DB::table('users')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
            'model_has_roles' => DB::table('model_has_roles')
                ->where('tenant_id', $tenantId)
                ->orderBy('role_id')
                ->orderBy('model_id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
            'model_has_permissions' => DB::table('model_has_permissions')
                ->where('tenant_id', $tenantId)
                ->orderBy('permission_id')
                ->orderBy('model_id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
            'audit_events' => DB::table('audit_events')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function tenantMutationStates(array $tenantIds): array
    {
        $states = [];

        foreach (array_values(array_unique($tenantIds)) as $tenantId) {
            $states[$tenantId] = $this->tenantMutationState($tenantId);
        }

        return $states;
    }

    private function tenantRole(Tenant $tenant, string $name): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['dashboard.view']);

        return $role;
    }

    private function assertAuthorizationFailure(callable $operation, string $code): AuthorizationException
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly succeeded instead of returning [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());

            return $exception;
        }
    }

    private function assertDomainFailure(callable $operation, string $code): DomainException
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly succeeded instead of returning [{$code}].");
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());

            return $exception;
        }
    }
}
