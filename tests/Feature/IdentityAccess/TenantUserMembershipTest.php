<?php

namespace Tests\Feature\IdentityAccess;

use App\Domain\IdentityAccess\Actions\AssignTenantRoles;
use App\Domain\IdentityAccess\Actions\CreateTenantUser;
use App\Domain\IdentityAccess\Actions\DeactivateTenantUser;
use App\Domain\IdentityAccess\Actions\UpdateTenantUser;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantUserMembershipTest extends TestCase
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

    public function test_tenant_user_lifecycle_preserves_one_tenant_authorship_and_audit_and_returns_open_assignment_ids(): void
    {
        [$administrator, $context] = $this->administratorContext(inactiveTenant: true);
        $role = $this->tenantRole($context->tenant, 'Tenant Member', ['dashboard.view']);
        $initialEmail = 'member-'.str()->uuid().'@example.test';
        $plainPassword = 'installation-specific-password';
        $createCorrelation = (string) str()->uuid();
        $updateCorrelation = (string) str()->uuid();
        $retainEmailCorrelation = (string) str()->uuid();
        $deactivateCorrelation = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1401);

        $user = app(CreateTenantUser::class)->execute(
            $administrator,
            $context,
            'Initial Member',
            $initialEmail,
            $plainPassword,
            [$role],
            $createCorrelation,
        );

        $this->assertSame(1401, $registrar->getPermissionsTeamId());
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($context->tenantId, (int) $user->tenant_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
        $originalPasswordHash = $user->password;
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);

        $updatedEmail = 'updated-'.str()->uuid().'@example.test';
        $registrar->setPermissionsTeamId(1402);
        $updated = app(UpdateTenantUser::class)->execute(
            $administrator,
            $context,
            $user,
            'Updated Member',
            $updatedEmail,
            $updateCorrelation,
        );

        $this->assertSame(1402, $registrar->getPermissionsTeamId());
        $this->assertSame($user->getKey(), $updated->getKey());
        $this->assertSame('Updated Member', $updated->name);
        $this->assertSame($updatedEmail, $updated->email);
        $this->assertSame($context->tenantId, (int) $updated->tenant_id);
        $this->assertSame($originalPasswordHash, $updated->password);

        $registrar->setPermissionsTeamId(14021);
        $retainedEmail = app(UpdateTenantUser::class)->execute(
            $administrator,
            $context,
            $updated,
            'Retained Email Member',
            $updatedEmail,
            $retainEmailCorrelation,
        );

        $this->assertSame(14021, $registrar->getPermissionsTeamId());
        $this->assertSame($updated->getKey(), $retainedEmail->getKey());
        $this->assertSame('Retained Email Member', $retainedEmail->name);
        $this->assertSame($updatedEmail, $retainedEmail->email);
        $this->assertSame($originalPasswordHash, $retainedEmail->password);

        $context->tenant->forceFill([
            'created_by_user_id' => $updated->getKey(),
            'state_changed_by_user_id' => $updated->getKey(),
            'state_changed_at' => now(),
        ])->save();
        $historicalAudit = AuditEvent::query()->create([
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $updated->getKey(),
            'actor_label' => $updated->name,
            'event_type' => 'test.authorship.preserved',
            'subject_type' => $context->tenant->getMorphClass(),
            'subject_id' => $context->tenantId,
            'correlation_id' => (string) str()->uuid(),
            'properties' => ['evidence' => 'pre-deactivation'],
            'occurred_at' => now('UTC'),
        ]);
        $openAssignmentIds = [901, 17, 405, 17, 901];

        $registrar->setPermissionsTeamId(1403);
        $reassignmentNeeded = app(DeactivateTenantUser::class)->execute(
            $administrator,
            $context,
            $updated,
            $openAssignmentIds,
            $deactivateCorrelation,
        );

        $this->assertSame(1403, $registrar->getPermissionsTeamId());
        $this->assertSame([17, 405, 901], $reassignmentNeeded);
        $updated->refresh();
        $context->tenant->refresh();
        $historicalAudit->refresh();
        $this->assertFalse($updated->is_active);
        $this->assertSame($originalPasswordHash, $updated->password);
        $this->assertSame($context->tenantId, (int) $updated->tenant_id);
        $this->assertSame($updated->getKey(), $context->tenant->created_by_user_id);
        $this->assertSame($updated->getKey(), $context->tenant->state_changed_by_user_id);
        $this->assertSame($updated->getKey(), $historicalAudit->actor_user_id);
        $this->assertSame($context->tenantId, $historicalAudit->tenant_id);
        $this->assertSame(['evidence' => 'pre-deactivation'], $historicalAudit->properties);
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $updated->getKey(),
        ]);
        $this->assertMinimizedAuditEvent('tenant.user.created', $createCorrelation, $administrator, $context->tenant, $updated, [$plainPassword, $originalPasswordHash]);
        $this->assertMinimizedAuditEvent('tenant.user.updated', $updateCorrelation, $administrator, $context->tenant, $updated, [$plainPassword, $originalPasswordHash]);
        $this->assertMinimizedAuditEvent('tenant.user.updated', $retainEmailCorrelation, $administrator, $context->tenant, $updated, [$plainPassword, $originalPasswordHash]);
        $this->assertMinimizedAuditEvent('tenant.user.deactivated', $deactivateCorrelation, $administrator, $context->tenant, $updated, [$plainPassword, $originalPasswordHash]);
    }

    public function test_user_fields_are_validated_with_zero_side_effects(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Email Validation Role', ['dashboard.view']);
        $existing = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'email' => 'existing-'.str()->uuid().'@example.test',
        ]);
        $target = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Unchanged Target',
            'email' => 'target-'.str()->uuid().'@example.test',
        ]);
        $targetOriginalEmail = $target->email;
        $targetOriginalPassword = $target->password;
        $blankNameEmail = 'blank-name-'.str()->uuid().'@example.test';
        $blankPasswordEmail = 'blank-password-'.str()->uuid().'@example.test';
        $beforeUsers = User::query()->count();
        $registrar = app(PermissionRegistrar::class);
        $cases = [
            ['name', fn () => app(CreateTenantUser::class)->execute($administrator, $context, '   ', $blankNameEmail, 'temporary-password', [$role], (string) str()->uuid())],
            ['email', fn () => app(CreateTenantUser::class)->execute($administrator, $context, 'Blank Email', '   ', 'temporary-password', [$role], (string) str()->uuid())],
            ['password', fn () => app(CreateTenantUser::class)->execute($administrator, $context, 'Blank Password', $blankPasswordEmail, '   ', [$role], (string) str()->uuid())],
            ['email', fn () => app(CreateTenantUser::class)->execute($administrator, $context, 'Invalid Email', 'not-an-email', 'temporary-password', [$role], (string) str()->uuid())],
            ['email', fn () => app(CreateTenantUser::class)->execute($administrator, $context, 'Duplicate Email', $existing->email, 'temporary-password', [$role], (string) str()->uuid())],
            ['name', fn () => app(UpdateTenantUser::class)->execute($administrator, $context, $target, '   ', $targetOriginalEmail, (string) str()->uuid())],
            ['email', fn () => app(UpdateTenantUser::class)->execute($administrator, $context, $target, 'Blank Email Update', '   ', (string) str()->uuid())],
            ['email', fn () => app(UpdateTenantUser::class)->execute($administrator, $context, $target, 'Invalid Update', 'not-an-email', (string) str()->uuid())],
            ['email', fn () => app(UpdateTenantUser::class)->execute($administrator, $context, $target, 'Duplicate Update', $existing->email, (string) str()->uuid())],
        ];

        foreach ($cases as $index => [$field, $operation]) {
            $sentinel = 1450 + $index;
            $registrar->setPermissionsTeamId($sentinel);
            $this->assertValidationFailure($operation, $field);
            $this->assertSame($sentinel, $registrar->getPermissionsTeamId());
        }

        $target->refresh();
        $this->assertSame($beforeUsers, User::query()->count());
        $this->assertSame('Unchanged Target', $target->name);
        $this->assertSame($targetOriginalEmail, $target->email);
        $this->assertSame($targetOriginalPassword, $target->password);
        $this->assertDatabaseMissing('users', ['email' => $blankNameEmail]);
        $this->assertDatabaseMissing('users', ['email' => $blankPasswordEmail]);
        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);
        $this->assertDatabaseMissing('audit_events', ['tenant_id' => $context->tenantId]);
    }

    public function test_create_tenant_user_rejects_an_empty_role_set_without_side_effects(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $email = 'empty-role-create-'.str()->uuid().'@example.test';
        $correlationId = (string) str()->uuid();
        $before = $this->tenantUserMutationState($context->tenantId);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1460);

        $this->assertValidationFailure(
            fn () => app(CreateTenantUser::class)->execute(
                $administrator,
                $context,
                'Empty Role User',
                $email,
                'temporary-password',
                [],
                $correlationId,
            ),
            'roles',
        );

        $this->assertSame(1460, $registrar->getPermissionsTeamId());
        $this->assertSame($before, $this->tenantUserMutationState($context->tenantId));
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_assign_tenant_roles_rejects_an_empty_complete_set_and_preserves_existing_roles(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $existingRole = $this->tenantRole($context->tenant, 'Required Existing Role', ['dashboard.view']);
        $target = User::factory()->create(['tenant_id' => $context->tenantId]);
        DB::table('model_has_roles')->insert([
            'tenant_id' => $context->tenantId,
            'role_id' => $existingRole->getKey(),
            'model_id' => $target->getKey(),
            'model_type' => $target->getMorphClass(),
        ]);
        $correlationId = (string) str()->uuid();
        $before = $this->tenantUserMutationState($context->tenantId);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1461);

        $this->assertValidationFailure(
            fn () => app(AssignTenantRoles::class)->execute(
                $administrator,
                $context,
                $target,
                [],
                $correlationId,
            ),
            'roles',
        );

        $this->assertSame(1461, $registrar->getPermissionsTeamId());
        $this->assertSame($before, $this->tenantUserMutationState($context->tenantId));
        $this->assertDatabaseHas('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $existingRole->getKey(),
            'model_id' => $target->getKey(),
            'model_type' => $target->getMorphClass(),
        ]);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_user_and_role_actions_reject_foreign_or_tenantless_targets_without_mutation(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $foreignTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign User',
            'email' => 'foreign-'.str()->uuid().'@example.test',
        ]);
        $tenantlessUser = User::factory()->create([
            'tenant_id' => null,
            'name' => 'Tenantless User',
            'email' => 'tenantless-'.str()->uuid().'@example.test',
        ]);
        $localRole = $this->tenantRole($context->tenant, 'Local Role', ['dashboard.view']);
        $foreignRole = $this->tenantRole($foreignTenant, 'Foreign Role', ['dashboard.view']);
        $registrar = app(PermissionRegistrar::class);

        foreach ([$foreignUser, $tenantlessUser] as $index => $target) {
            $originalName = $target->name;
            $originalEmail = $target->email;
            $target->forceFill(['tenant_id' => $context->tenantId]);

            $registrar->setPermissionsTeamId(1500 + ($index * 10));
            $this->assertDomainFailure(
                fn () => app(UpdateTenantUser::class)->execute(
                    $administrator,
                    $context,
                    $target,
                    'Cross Tenant Mutation',
                    'mutated-'.str()->uuid().'@example.test',
                    (string) str()->uuid(),
                ),
                'TENANT_RELATION_MISMATCH',
            );
            $this->assertSame(1500 + ($index * 10), $registrar->getPermissionsTeamId());
            $registrar->setPermissionsTeamId(1501 + ($index * 10));
            $this->assertDomainFailure(
                fn () => app(DeactivateTenantUser::class)->execute(
                    $administrator,
                    $context,
                    $target,
                    [11, 22],
                    (string) str()->uuid(),
                ),
                'TENANT_RELATION_MISMATCH',
            );
            $this->assertSame(1501 + ($index * 10), $registrar->getPermissionsTeamId());

            $target->refresh();
            $this->assertSame($originalName, $target->name);
            $this->assertSame($originalEmail, $target->email);
            $this->assertTrue($target->is_active);
        }

        $foreignUser->forceFill(['tenant_id' => $context->tenantId]);
        $registrar->setPermissionsTeamId(1520);
        $this->assertDomainFailure(
            fn () => app(AssignTenantRoles::class)->execute(
                $administrator,
                $context,
                $foreignUser,
                [$localRole],
                (string) str()->uuid(),
            ),
            'TENANT_RELATION_MISMATCH',
        );
        $this->assertSame(1520, $registrar->getPermissionsTeamId());
        $localUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $foreignRole->forceFill(['tenant_id' => $context->tenantId]);
        $registrar->setPermissionsTeamId(1521);
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
        $this->assertSame(1521, $registrar->getPermissionsTeamId());
        $createEmail = 'foreign-role-create-'.str()->uuid().'@example.test';
        $registrar->setPermissionsTeamId(1522);
        $this->assertDomainFailure(
            fn () => app(CreateTenantUser::class)->execute(
                $administrator,
                $context,
                'Foreign Role Create',
                $createEmail,
                'temporary-password',
                [$foreignRole],
                (string) str()->uuid(),
            ),
            'TENANT_RELATION_MISMATCH',
        );
        $this->assertSame(1522, $registrar->getPermissionsTeamId());
        $this->assertDatabaseMissing('users', ['email' => $createEmail]);

        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $localRole->getKey(),
            'model_id' => $foreignUser->getKey(),
        ]);
        $this->assertDatabaseMissing('model_has_roles', [
            'tenant_id' => $context->tenantId,
            'role_id' => $foreignRole->getKey(),
        ]);
    }

    public function test_create_tenant_user_audit_failure_rolls_back_user_and_initial_roles(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Create User Role', ['dashboard.view']);
        $email = 'create-rollback-'.str()->uuid().'@example.test';
        $correlationId = (string) str()->uuid();
        $createdUserId = null;
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1601);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $context, $role, $email, &$createdUserId): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $createdUserId = User::query()->where('email', $email)->value('id');
            $this->assertNotNull($createdUserId, 'The audit write occurred before the user mutation.');
            $this->assertDatabaseHas('model_has_roles', [
                'tenant_id' => $context->tenantId,
                'role_id' => $role->getKey(),
                'model_id' => $createdUserId,
            ]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreateTenantUser::class);
            $action->execute(
                $administrator,
                $context,
                'Rollback User',
                $email,
                'installation-specific-password',
                [$role],
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(1601, $registrar->getPermissionsTeamId());
            $this->assertDatabaseMissing('users', ['email' => $email]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);

            if ($createdUserId !== null) {
                $this->assertDatabaseMissing('model_has_roles', [
                    'tenant_id' => $context->tenantId,
                    'model_id' => $createdUserId,
                ]);
            }
        }
    }

    public function test_update_tenant_user_audit_failure_rolls_back_identity_fields(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $user = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'name' => 'Original User',
            'email' => 'original-'.str()->uuid().'@example.test',
        ]);
        $originalEmail = $user->email;
        $changedEmail = 'changed-'.str()->uuid().'@example.test';
        $correlationId = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1602);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $user, $changedEmail): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $this->assertDatabaseHas('users', [
                'id' => $user->getKey(),
                'name' => 'Changed User',
                'email' => $changedEmail,
            ]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateTenantUser::class);
            $action->execute(
                $administrator,
                $context,
                $user,
                'Changed User',
                $changedEmail,
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(1602, $registrar->getPermissionsTeamId());
            $this->assertDatabaseHas('users', [
                'id' => $user->getKey(),
                'name' => 'Original User',
                'email' => $originalEmail,
            ]);
            $this->assertDatabaseMissing('users', [
                'id' => $user->getKey(),
                'name' => 'Changed User',
                'email' => $changedEmail,
            ]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_deactivate_tenant_user_audit_failure_rolls_back_state_and_preserves_history(): void
    {
        [$administrator, $context] = $this->administratorContext();
        $user = User::factory()->create(['tenant_id' => $context->tenantId, 'is_active' => true]);
        $context->tenant->forceFill([
            'created_by_user_id' => $user->getKey(),
            'state_changed_by_user_id' => $user->getKey(),
            'state_changed_at' => now(),
        ])->save();
        $historicalAudit = AuditEvent::query()->create([
            'tenant_id' => $context->tenantId,
            'actor_user_id' => $user->getKey(),
            'actor_label' => $user->name,
            'event_type' => 'test.deactivation.rollback-history',
            'subject_type' => $context->tenant->getMorphClass(),
            'subject_id' => $context->tenantId,
            'correlation_id' => (string) str()->uuid(),
            'properties' => ['evidence' => 'immutable'],
            'occurred_at' => now('UTC'),
        ]);
        $correlationId = (string) str()->uuid();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(1603);
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $user): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'is_active' => false]);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeactivateTenantUser::class);
            $action->execute(
                $administrator,
                $context,
                $user,
                [203, 9, 77],
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(1603, $registrar->getPermissionsTeamId());
            $this->assertDatabaseHas('users', [
                'id' => $user->getKey(),
                'tenant_id' => $context->tenantId,
                'is_active' => true,
            ]);
            $this->assertDatabaseMissing('users', ['id' => $user->getKey(), 'is_active' => false]);
            $this->assertDatabaseHas('tenants', [
                'id' => $context->tenantId,
                'created_by_user_id' => $user->getKey(),
                'state_changed_by_user_id' => $user->getKey(),
            ]);
            $this->assertDatabaseHas('audit_events', [
                'id' => $historicalAudit->getKey(),
                'tenant_id' => $context->tenantId,
                'actor_user_id' => $user->getKey(),
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

    /** @return array<string, list<array<string, mixed>>> */
    private function tenantUserMutationState(int $tenantId): array
    {
        return [
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

    /** @param list<string> $secretValues */
    private function assertMinimizedAuditEvent(
        string $eventType,
        string $correlationId,
        User $actor,
        Tenant $tenant,
        User $subject,
        array $secretValues,
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

        $encoded = json_encode($event->properties, JSON_THROW_ON_ERROR);
        foreach ($secretValues as $secretValue) {
            $this->assertStringNotContainsString($secretValue, $encoded);
        }
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
}
