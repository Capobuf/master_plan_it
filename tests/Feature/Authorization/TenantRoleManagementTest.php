<?php

namespace Tests\Feature\Authorization;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\CreateTenantRole;
use App\Domain\IdentityAccess\Actions\DeleteTenantRole;
use App\Domain\IdentityAccess\Actions\UpdateTenantRole;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantRoleManagementTest extends TestCase
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

        parent::tearDown();
    }

    public function test_administrator_can_create_update_and_delete_a_tenant_role_by_exact_abilities(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $registrar = app(PermissionRegistrar::class);
        $createCorrelation = (string) str()->uuid();
        $updateCorrelation = (string) str()->uuid();
        $retainNameCorrelation = (string) str()->uuid();
        $deleteCorrelation = (string) str()->uuid();
        $otherTenant = Tenant::factory()->create();
        $sameNameOtherTenant = $this->tenantRole($otherTenant, 'Operations Planner', ['dashboard.view']);

        $registrar->setPermissionsTeamId(701);

        $created = app(CreateTenantRole::class)->execute(
            $administrator,
            $context,
            'Operations Planner',
            ['dashboard.view', 'planning-year.view'],
            $createCorrelation,
        );

        $this->assertSame(701, $registrar->getPermissionsTeamId());
        $this->assertInstanceOf(Role::class, $created);
        $this->assertNotSame($sameNameOtherTenant->getKey(), $created->getKey());
        $this->assertSame($context->tenantId, (int) $created->tenant_id);
        $this->assertSame(
            ['dashboard.view', 'planning-year.view'],
            $created->permissions()->orderBy('name')->pluck('name')->all(),
        );

        $registrar->setPermissionsTeamId(702);
        $updated = app(UpdateTenantRole::class)->execute(
            $administrator,
            $context,
            $created,
            'Planning Coordinator',
            ['planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate', 'planning-year.view'],
            $updateCorrelation,
        );

        $this->assertSame(702, $registrar->getPermissionsTeamId());
        $this->assertSame($created->getKey(), $updated->getKey());
        $this->assertSame('Planning Coordinator', $updated->name);
        $this->assertSame(
            ['planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate', 'planning-year.view'],
            $updated->permissions()->orderBy('name')->pluck('name')->all(),
        );

        $registrar->setPermissionsTeamId(7021);
        $retainedName = app(UpdateTenantRole::class)->execute(
            $administrator,
            $context,
            $updated,
            'Planning Coordinator',
            ['planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate', 'planning-year.view'],
            $retainNameCorrelation,
        );

        $this->assertSame(7021, $registrar->getPermissionsTeamId());
        $this->assertSame($updated->getKey(), $retainedName->getKey());
        $this->assertSame('Planning Coordinator', $retainedName->name);

        $registrar->setPermissionsTeamId(703);
        app(DeleteTenantRole::class)->execute(
            $administrator,
            $context,
            $retainedName,
            $deleteCorrelation,
        );

        $this->assertSame(703, $registrar->getPermissionsTeamId());
        $this->assertDatabaseMissing('roles', ['id' => $retainedName->getKey()]);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $retainedName->getKey()]);
        $this->assertDatabaseHas('roles', [
            'id' => $sameNameOtherTenant->getKey(),
            'tenant_id' => $otherTenant->getKey(),
            'name' => 'Operations Planner',
        ]);
        $this->assertMinimizedAuditEvent('tenant.role.created', $createCorrelation, $administrator, $context->tenant, $created);
        $this->assertMinimizedAuditEvent('tenant.role.updated', $updateCorrelation, $administrator, $context->tenant, $updated);
        $this->assertMinimizedAuditEvent('tenant.role.updated', $retainNameCorrelation, $administrator, $context->tenant, $retainedName);
        $this->assertMinimizedAuditEvent('tenant.role.deleted', $deleteCorrelation, $administrator, $context->tenant, $retainedName);
    }

    public function test_multiple_arbitrarily_named_tenant_roles_grant_an_additive_permission_union(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $viewer = $this->tenantRole($context->tenant, 'Calendar Observer', ['planning-year.view']);
        $lifecycle = $this->tenantRole(
            $context->tenant,
            'Calendar Lifecycle',
            ['planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate'],
        );
        $previous = $this->tenantRole($context->tenant, 'Previous Complete Set', ['vendor.update']);
        $user = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->assignRoleDirectly($user, $context->tenantId, $previous);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(777);
        $correlationId = (string) str()->uuid();

        $assigned = app(AssignTenantRoles::class)->execute(
            $administrator,
            $context,
            $user,
            [$viewer, $lifecycle],
            $correlationId,
        );

        $this->assertInstanceOf(User::class, $assigned);
        $this->assertSame($user->getKey(), $assigned->getKey());
        $this->assertSame(777, $registrar->getPermissionsTeamId());
        $this->assertDatabaseCount('model_has_permissions', 0);
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $previous->getKey(),
            'model_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $viewer->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $lifecycle->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);

        $registrar->setPermissionsTeamId($context->tenantId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        foreach (['planning-year.view', 'planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate'] as $ability) {
            $this->assertTrue($user->can($ability), "The additive role union omitted [{$ability}].");
        }

        $this->assertFalse($user->can('vendor.update'));
        $this->assertMinimizedAuditEvent('tenant.user.roles-updated', $correlationId, $administrator, $context->tenant, $user);
    }

    public function test_role_fields_and_catalogue_membership_are_validated_before_any_write(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $existing = $this->tenantRole($context->tenant, 'Existing Tenant Role', ['dashboard.view']);
        $updateTarget = $this->tenantRole($context->tenant, 'Update Target Role', ['planning-year.view']);
        $beforeRoles = Role::query()->where('tenant_id', $context->tenantId)->count();
        $registrar = app(PermissionRegistrar::class);

        $cases = [
            ['name', fn () => app(CreateTenantRole::class)->execute($administrator, $context, '   ', ['dashboard.view'], (string) str()->uuid())],
            ['name', fn () => app(CreateTenantRole::class)->execute($administrator, $context, 'Existing Tenant Role', ['dashboard.view'], (string) str()->uuid())],
            ['name', fn () => app(UpdateTenantRole::class)->execute($administrator, $context, $updateTarget, '   ', ['planning-year.view'], (string) str()->uuid())],
            ['name', fn () => app(UpdateTenantRole::class)->execute($administrator, $context, $updateTarget, 'Existing Tenant Role', ['planning-year.view'], (string) str()->uuid())],
            ['abilities', fn () => app(CreateTenantRole::class)->execute($administrator, $context, 'Unknown Ability Role', ['not.in.catalogue'], (string) str()->uuid())],
            ['abilities', fn () => app(UpdateTenantRole::class)->execute($administrator, $context, $existing, 'Unsafe Update', ['not.in.catalogue'], (string) str()->uuid())],
        ];

        foreach ($cases as $index => [$field, $operation]) {
            $sentinel = 810 + $index;
            $registrar->setPermissionsTeamId($sentinel);
            $this->assertValidationFailure($operation, $field);
            $this->assertSame($sentinel, $registrar->getPermissionsTeamId());
        }

        $this->assertSame($beforeRoles, Role::query()->where('tenant_id', $context->tenantId)->count());
        $this->assertDatabaseHas('roles', ['id' => $existing->getKey(), 'name' => 'Existing Tenant Role']);
        $this->assertDatabaseHas('roles', ['id' => $updateTarget->getKey(), 'name' => 'Update Target Role']);
        $this->assertSame(
            ['dashboard.view'],
            Role::query()->findOrFail($existing->getKey())->permissions()->pluck('name')->all(),
        );
        $this->assertSame(
            ['planning-year.view'],
            Role::query()->findOrFail($updateTarget->getKey())->permissions()->pluck('name')->all(),
        );
        $this->assertDatabaseMissing('roles', ['tenant_id' => $context->tenantId, 'name' => 'Unknown Ability Role']);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'tenant.role.created', 'tenant_id' => $context->tenantId]);
    }

    public function test_create_tenant_role_rejects_a_persisted_non_catalogue_ability_without_side_effects(): void
    {
        [$administrator, $context] = $this->administratorContext();
        Permission::query()->firstOrCreate([
            'name' => 'rogue.ability',
            'guard_name' => 'web',
        ]);
        $correlationId = (string) str()->uuid();
        $before = $this->tenantRoleMutationState($context->tenantId);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(815);

        $this->assertValidationFailure(
            fn () => app(CreateTenantRole::class)->execute(
                $administrator,
                $context,
                'Rogue Ability Create',
                ['rogue.ability'],
                $correlationId,
            ),
            'abilities.0',
        );

        $this->assertSame(815, $registrar->getPermissionsTeamId());
        $this->assertSame($before, $this->tenantRoleMutationState($context->tenantId));
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_update_tenant_role_rejects_a_persisted_non_catalogue_ability_without_side_effects(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Catalogue-Safe Role', ['dashboard.view']);
        Permission::query()->firstOrCreate([
            'name' => 'rogue.ability',
            'guard_name' => 'web',
        ]);
        $correlationId = (string) str()->uuid();
        $before = $this->tenantRoleMutationState($context->tenantId);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(816);

        $this->assertValidationFailure(
            fn () => app(UpdateTenantRole::class)->execute(
                $administrator,
                $context,
                $role,
                'Rogue Ability Update',
                ['rogue.ability'],
                $correlationId,
            ),
            'abilities.0',
        );

        $this->assertSame(816, $registrar->getPermissionsTeamId());
        $this->assertSame($before, $this->tenantRoleMutationState($context->tenantId));
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_seeded_editor_and_viewer_templates_match_the_exact_approved_catalogue(): void
    {
        $editor = Role::query()
            ->where('name', 'Editor')
            ->where('guard_name', 'web')
            ->whereNull('tenant_id')
            ->firstOrFail();
        $viewer = Role::query()
            ->where('name', 'Viewer')
            ->where('guard_name', 'web')
            ->whereNull('tenant_id')
            ->firstOrFail();

        $this->assertSame(self::editorAbilities(), $editor->permissions()->orderBy('name')->pluck('name')->all());
        $this->assertSame(self::viewerAbilities(), $viewer->permissions()->orderBy('name')->pluck('name')->all());
        $this->assertContains('planning-year.view', self::editorAbilities());
        $this->assertSame(
            [],
            array_values(array_intersect(
                ['planning-year.create', 'planning-year.deactivate', 'planning-year.reactivate'],
                self::editorAbilities(),
            )),
        );
    }

    public function test_foreign_tenant_roles_cannot_be_updated_deleted_or_assigned(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $foreignTenant = Tenant::factory()->create();
        $foreignRole = $this->tenantRole($foreignTenant, 'Foreign Role', ['dashboard.view']);
        $localUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $foreignRole->forceFill(['tenant_id' => $context->tenantId]);
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId(821);
        $this->assertDomainFailure(
            fn () => app(UpdateTenantRole::class)->execute(
                $administrator,
                $context,
                $foreignRole,
                'Leaked Rename',
                ['planning-year.view'],
                (string) str()->uuid(),
            ),
            'TENANT_RELATION_MISMATCH',
        );
        $this->assertSame(821, $registrar->getPermissionsTeamId());
        $registrar->setPermissionsTeamId(822);
        $this->assertDomainFailure(
            fn () => app(DeleteTenantRole::class)->execute(
                $administrator,
                $context,
                $foreignRole,
                (string) str()->uuid(),
            ),
            'TENANT_RELATION_MISMATCH',
        );
        $this->assertSame(822, $registrar->getPermissionsTeamId());
        $registrar->setPermissionsTeamId(823);
        $this->assertDomainFailure(
            fn () => app(AssignTenantRoles::class)->execute(
                $administrator,
                $context,
                $localUser,
                [$foreignRole],
                (string) str()->uuid(),
            ),
            'TENANT_RELATION_MISMATCH',
        );
        $this->assertSame(823, $registrar->getPermissionsTeamId());

        $this->assertDatabaseHas('roles', [
            'id' => $foreignRole->getKey(),
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign Role',
        ]);
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $foreignRole->getKey(),
            'model_id' => $localUser->getKey(),
        ]);
    }

    public function test_delete_tenant_role_rejects_the_only_role_of_a_user_but_allows_deletion_when_another_role_remains(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Only Assigned Role', ['dashboard.view']);
        $remainingRole = $this->tenantRole($context->tenant, 'Remaining Role', ['planning-year.view']);
        $user = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->assignRoleDirectly($user, $context->tenantId, $role);
        $rejectedCorrelationId = (string) str()->uuid();
        $before = $this->tenantRoleMutationState($context->tenantId);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(824);

        $this->assertDomainFailure(
            fn () => app(DeleteTenantRole::class)->execute(
                $administrator,
                $context,
                $role,
                $rejectedCorrelationId,
            ),
            'TENANT_ROLE_IN_USE',
        );

        $this->assertSame(824, $registrar->getPermissionsTeamId());
        $this->assertSame($before, $this->tenantRoleMutationState($context->tenantId));
        $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $rejectedCorrelationId]);

        $this->assignRoleDirectly($user, $context->tenantId, $remainingRole);
        $acceptedCorrelationId = (string) str()->uuid();
        $registrar->setPermissionsTeamId(825);

        app(DeleteTenantRole::class)->execute(
            $administrator,
            $context,
            $role,
            $acceptedCorrelationId,
        );

        $this->assertSame(825, $registrar->getPermissionsTeamId());
        $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $remainingRole->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
        $this->assertSame(
            1,
            DB::table('model_has_roles')
                ->where('tenant_id', $context->tenantId)
                ->where('model_id', $user->getKey())
                ->where('model_type', $user->getMorphClass())
                ->count(),
        );
        $this->assertMinimizedAuditEvent(
            'tenant.role.deleted',
            $acceptedCorrelationId,
            $administrator,
            $context->tenant,
            $role,
        );
    }

    public function test_create_tenant_role_audit_failure_rolls_back_role_and_permissions(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $correlationId = (string) str()->uuid();
        $roleName = 'Rollback Create '.str()->uuid();
        $createdRoleId = null;
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(831);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $context, $roleName, &$createdRoleId): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $createdRoleId = Role::query()
                ->where('tenant_id', $context->tenantId)
                ->where('name', $roleName)
                ->value('id');
            $this->assertNotNull($createdRoleId, 'The audit write occurred before the role mutation.');
            $this->assertDatabaseHas('role_has_permissions', ['role_id' => $createdRoleId]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreateTenantRole::class);
            $action->execute(
                $administrator,
                $context,
                $roleName,
                ['dashboard.view'],
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(831, $registrar->getPermissionsTeamId());
            $this->assertDatabaseMissing('roles', ['tenant_id' => $context->tenantId, 'name' => $roleName]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);

            if ($createdRoleId !== null) {
                $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $createdRoleId]);
            }
        }
    }

    public function test_update_tenant_role_audit_failure_rolls_back_name_and_permissions(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Original Role', ['dashboard.view']);
        $correlationId = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(832);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $role): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $this->assertDatabaseHas('roles', ['id' => $role->getKey(), 'name' => 'Changed Role']);
            $this->assertSame(
                ['planning-year.view'],
                Role::query()->findOrFail($role->getKey())->permissions()->orderBy('name')->pluck('name')->all(),
            );

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateTenantRole::class);
            $action->execute(
                $administrator,
                $context,
                $role,
                'Changed Role',
                ['planning-year.view'],
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(832, $registrar->getPermissionsTeamId());
            $this->assertDatabaseHas('roles', ['id' => $role->getKey(), 'name' => 'Original Role']);
            $this->assertDatabaseMissing('roles', ['id' => $role->getKey(), 'name' => 'Changed Role']);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
            $this->assertSame(
                ['dashboard.view'],
                Role::query()->findOrFail($role->getKey())->permissions()->orderBy('name')->pluck('name')->all(),
            );
        }
    }

    public function test_delete_tenant_role_audit_failure_restores_role_and_permissions(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Delete Rollback', ['dashboard.view']);
        $permissionId = $role->permissions()->value('permissions.id');
        $correlationId = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(833);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $role): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
            $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->getKey()]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeleteTenantRole::class);
            $action->execute($administrator, $context, $role, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(833, $registrar->getPermissionsTeamId());
            $this->assertDatabaseHas('roles', [
                'id' => $role->getKey(),
                'tenant_id' => $context->tenantId,
                'name' => 'Delete Rollback',
            ]);
            $this->assertDatabaseHas('role_has_permissions', [
                'role_id' => $role->getKey(),
                'permission_id' => $permissionId,
            ]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_assign_tenant_roles_audit_failure_restores_the_previous_role_set(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $previousRole = $this->tenantRole($context->tenant, 'Previous Role', ['dashboard.view']);
        $replacementRole = $this->tenantRole($context->tenant, 'Replacement Role', ['planning-year.view']);
        $user = User::factory()->create(['tenant_id' => $context->tenantId]);
        $this->assignRoleDirectly($user, $context->tenantId, $previousRole);
        $correlationId = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(834);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $context, $user, $previousRole, $replacementRole): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $this->assertDatabaseMissing('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $previousRole->getKey(),
                'model_id' => $user->getKey(),
            ]);
            $this->assertDatabaseHas('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $replacementRole->getKey(),
                'model_id' => $user->getKey(),
            ]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(AssignTenantRoles::class);
            $action->execute(
                $administrator,
                $context,
                $user,
                [$replacementRole],
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(834, $registrar->getPermissionsTeamId());
            $this->assertDatabaseHas('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $previousRole->getKey(),
                'model_id' => $user->getKey(),
            ]);
            $this->assertDatabaseMissing('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $replacementRole->getKey(),
                'model_id' => $user->getKey(),
            ]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    /** @return array{User, TenantContext} */
    private function administratorContext(bool $inactiveTenant = false): array
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create([
            'state' => $inactiveTenant ? TenantState::Inactive : TenantState::Active,
        ]);

        return [$administrator, new TenantContext($tenant, $administrator)];
    }

    /** @param list<string> $abilities */
    private function tenantRole(Tenant $tenant, string $name, array $abilities): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);

        return $role;
    }

    private function assignRoleDirectly(User $user, int $tenantId, Role $role): void
    {
        DB::table('model_has_roles')->insert([
            'tenant_id' => $tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function tenantRoleMutationState(int $tenantId): array
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
            'model_has_roles' => DB::table('model_has_roles')
                ->where('tenant_id', $tenantId)
                ->orderBy('role_id')
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

    private function assertValidationFailure(callable $operation, string $field): ValidationException
    {
        try {
            $operation();
            $this->fail("Operation unexpectedly accepted invalid [{$field}] input.");
        } catch (ValidationException $exception) {
            $fields = array_keys($exception->errors());
            $this->assertTrue(
                in_array($field, $fields, true)
                    || collect($fields)->contains(fn (string $key): bool => str_starts_with($key, $field.'.')),
                "ValidationException did not contain a [{$field}] field error.",
            );

            return $exception;
        }
    }

    private function assertMinimizedAuditEvent(
        string $eventType,
        string $correlationId,
        User $actor,
        Tenant $tenant,
        Role|User $subject,
    ): void {
        $this->assertSame(1, AuditEvent::query()->where('correlation_id', $correlationId)->count());
        $event = AuditEvent::query()->where('correlation_id', $correlationId)->firstOrFail();

        $this->assertSame($eventType, $event->event_type);
        $this->assertSame($actor->getKey(), $event->actor_user_id);
        $this->assertSame($tenant->getKey(), $event->tenant_id);
        $this->assertSame($subject->getMorphClass(), $event->subject_type);
        $this->assertSame($subject->getKey(), $event->subject_id);
        $this->assertSame($correlationId, $event->correlation_id);
        $this->assertIsArray($event->properties);
        $this->assertAuditPropertiesHaveNoSensitiveKeys($event->properties);
    }

    /** @param array<int|string, mixed> $properties */
    private function assertAuditPropertiesHaveNoSensitiveKeys(array $properties): void
    {
        foreach ($properties as $key => $value) {
            if (is_string($key)) {
                $normalized = strtolower($key);

                foreach (['password', 'hash', 'token', 'secret', 'session', 'cookie', 'authorization', 'credential', 'payload'] as $fragment) {
                    $this->assertStringNotContainsString($fragment, $normalized);
                }
            }

            if (is_array($value)) {
                $this->assertAuditPropertiesHaveNoSensitiveKeys($value);
            }
        }
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function auditCreatingListeners(): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
    }

    /** @param array<int, mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);

        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }

    /** @return list<string> */
    private static function editorAbilities(): array
    {
        return self::sorted([
            'dashboard.view', 'audit.view', 'notification.view', 'planning-year.view',
            'cost-center.view', 'cost-center.create', 'cost-center.update', 'cost-center.delete', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.view-revisions', 'cost-center.restore-revision',
            'vendor.view', 'vendor.create', 'vendor.update', 'vendor.delete', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view-revisions', 'vendor.restore-revision',
            'expense.view', 'expense.create', 'expense.update', 'expense.delete', 'expense.view-revisions', 'expense.restore-revision', 'expense.confirm-actual', 'expense.print', 'expense.export',
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

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private static function sorted(array $abilities): array
    {
        sort($abilities);

        return $abilities;
    }
}
