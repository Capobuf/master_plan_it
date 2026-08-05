<?php

namespace Tests\Feature\IdentityAccess;

use App\Domain\IdentityAccess\Actions\InvalidateUserSessions;
use App\Domain\IdentityAccess\Actions\ResetTenantUserPassword;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Log\Logger as IlluminateLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Expectation;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasswordAdministrationTest extends TestCase
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

    public function test_administrator_reset_hashes_once_delegates_invalidation_rotates_remember_token_and_materializes_one_minimized_audit(): void
    {
        $this->assertTrue(class_exists(ResetTenantUserPassword::class), 'T001-013 must provide ResetTenantUserPassword.');
        $this->assertTrue(class_exists(InvalidateUserSessions::class), 'T001-013 must provide InvalidateUserSessions.');

        [$administrator, $context] = $this->administratorContext();
        $role = $this->tenantRole($context->tenant, 'Password reset target role');
        $target = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'password' => Hash::make('previous-password-fixture'),
            'remember_token' => str()->random(60),
        ]);
        $this->assignRoleDirectly($target, $context->tenantId, $role);
        $sameTenantUser = User::factory()->create(['tenant_id' => $context->tenantId]);
        $foreignUser = User::factory()->create(['tenant_id' => Tenant::factory()->create()->getKey()]);
        $this->insertSession($target, 'target-session-a');
        $this->insertSession($target, 'target-session-b');
        $this->insertSession($sameTenantUser, 'same-tenant-session');
        $this->insertSession($foreignUser, 'foreign-session');

        $secret = 'administrator-supplied-secret';
        $previousHash = (string) $target->password;
        $previousRememberToken = (string) $target->remember_token;
        $identityBeforeReset = $this->identityState($target);
        $targetRolesBeforeReset = $this->roleState($target);
        $administratorRolesBeforeReset = $this->roleState($administrator);
        $protectedRoleBeforeReset = $this->protectedAdministratorRoleState();
        $versionsBeforeReset = $this->tableState('versions');
        $notificationsBeforeReset = $this->tableState('notifications');
        $auditCount = AuditEvent::query()->count();
        $correlationId = (string) str()->uuid();

        $realHasher = app('hash');
        $hasherSpy = Mockery::spy($realHasher);
        $hashExpectation = $hasherSpy->shouldReceive('make');
        $this->assertInstanceOf(Expectation::class, $hashExpectation);
        $hashExpectation->with($secret)->once()->passthru();
        Hash::swap($hasherSpy);
        $invalidationSpy = Mockery::spy(app(InvalidateUserSessions::class));
        $invalidationExpectation = $invalidationSpy->shouldReceive('execute');
        $this->assertInstanceOf(Expectation::class, $invalidationExpectation);
        $invalidationExpectation
            ->withArgs(fn (User $user): bool => $user->is($target))
            ->once()
            ->passthru();
        $this->app->instance(InvalidateUserSessions::class, $invalidationSpy);
        $logHandler = new TestHandler;
        $logger = Log::channel();
        $this->assertInstanceOf(IlluminateLogger::class, $logger);
        $monolog = $logger->getLogger();
        $this->assertInstanceOf(MonologLogger::class, $monolog);
        $monolog->pushHandler($logHandler);

        try {
            $action = app(ResetTenantUserPassword::class);
            $action->execute($administrator, $context, $target, $secret, $correlationId);

            $target->refresh();
            $hashMatches = Hash::check($secret, (string) $target->password);
            $logRecords = $logHandler->getRecords();
        } finally {
            $monolog->popHandler();
            Hash::swap($realHasher);
        }

        $this->assertTrue($hashMatches);
        $this->assertNotSame($previousHash, $target->password);
        $this->assertNotSame($previousRememberToken, $target->remember_token);
        $this->assertNotSame('', (string) $target->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session-b']);
        $this->assertDatabaseHas('sessions', ['id' => 'same-tenant-session', 'user_id' => $sameTenantUser->getKey()]);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $foreignUser->getKey()]);
        $this->assertSame($identityBeforeReset, $this->identityState($target));
        $this->assertSame($targetRolesBeforeReset, $this->roleState($target));
        $this->assertSame($administratorRolesBeforeReset, $this->roleState($administrator));
        $this->assertSame($protectedRoleBeforeReset, $this->protectedAdministratorRoleState());
        $this->assertSame($versionsBeforeReset, $this->tableState('versions'));
        $this->assertSame($notificationsBeforeReset, $this->tableState('notifications'));
        $this->assertDatabaseCount('audit_events', $auditCount + 1);

        $events = AuditEvent::query()->where('correlation_id', $correlationId)->get();
        $this->assertCount(1, $events);
        $event = $events->sole();
        $this->assertSame('tenant.user.password-reset', $event->event_type);
        $this->assertSame($administrator->getKey(), $event->actor_user_id);
        $this->assertSame($administrator->name, $event->actor_label);
        $this->assertSame($context->tenantId, $event->tenant_id);
        $this->assertSame($target->getMorphClass(), $event->subject_type);
        $this->assertSame($target->getKey(), $event->subject_id);
        $this->assertSame([], $event->properties);

        $serializedTarget = $target->toArray();
        $this->assertArrayNotHasKey('password', $serializedTarget);
        $this->assertArrayNotHasKey('remember_token', $serializedTarget);
        $this->assertObservableSecretsAbsent($logRecords, [
            $secret,
            $previousHash,
            (string) $target->password,
            $previousRememberToken,
            (string) $target->remember_token,
            'target-session-a',
            'target-session-b',
        ]);
    }

    public function test_standalone_invalidator_deletes_only_target_sessions_and_rotates_only_target_remember_token(): void
    {
        $this->assertTrue(class_exists(InvalidateUserSessions::class), 'T001-013 must provide InvalidateUserSessions.');

        $target = User::factory()->create(['remember_token' => str()->random(60)]);
        $otherUser = User::factory()->create(['remember_token' => str()->random(60)]);
        $this->insertSession($target, 'invalidate-target-a');
        $this->insertSession($target, 'invalidate-target-b');
        $this->insertSession($otherUser, 'invalidate-other');
        $targetToken = (string) $target->remember_token;
        $otherToken = (string) $otherUser->remember_token;
        $targetIdentity = $this->identityState($target);
        $auditCount = AuditEvent::query()->count();

        $action = app(InvalidateUserSessions::class);
        $action->execute($target);

        $target->refresh();
        $otherUser->refresh();
        $this->assertNotSame($targetToken, $target->remember_token);
        $this->assertSame($otherToken, $otherUser->remember_token);
        $this->assertSame($targetIdentity, $this->identityState($target));
        $this->assertDatabaseMissing('sessions', ['id' => 'invalidate-target-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'invalidate-target-b']);
        $this->assertDatabaseHas('sessions', ['id' => 'invalidate-other', 'user_id' => $otherUser->getKey()]);
        $this->assertDatabaseCount('audit_events', $auditCount);

        $spoofSource = User::factory()->create(['remember_token' => str()->random(60)]);
        $spoofVictim = User::factory()->create(['remember_token' => str()->random(60)]);
        $this->insertSession($spoofSource, 'invalidate-spoof-source');
        $this->insertSession($spoofVictim, 'invalidate-spoof-victim');
        $spoofState = $this->securityState([$spoofSource, $spoofVictim]);
        $spoofSource->forceFill(['id' => $spoofVictim->getKey()]);

        $this->assertDomainFailure(fn () => app(InvalidateUserSessions::class)->execute($spoofSource));
        $this->assertSame($spoofState, $this->securityState([$spoofSource, $spoofVictim]));
        $this->assertDatabaseHas('sessions', ['id' => 'invalidate-spoof-source']);
        $this->assertDatabaseHas('sessions', ['id' => 'invalidate-spoof-victim']);
    }

    public function test_direct_reset_rejects_unprotected_inactive_mismatched_and_spoofed_identities_without_side_effects(): void
    {
        $this->assertTrue(class_exists(ResetTenantUserPassword::class), 'T001-013 must provide ResetTenantUserPassword.');

        [$administrator, $context] = $this->administratorContext();
        [$otherAdministrator] = $this->administratorContext();
        $target = User::factory()->create(['tenant_id' => $context->tenantId]);
        $foreignTarget = User::factory()->create(['tenant_id' => Tenant::factory()->create()->getKey()]);
        $globalTarget = User::factory()->create(['tenant_id' => null]);
        $unprotectedGlobal = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $tenantActor = User::factory()->create(['tenant_id' => $context->tenantId, 'is_active' => true]);
        $deactivatedAdministrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($deactivatedAdministrator);
        User::query()->whereKey($deactivatedAdministrator->getKey())->update(['is_active' => false]);
        $this->insertSession($target, 'authorization-target-session');
        $this->insertSession($foreignTarget, 'authorization-foreign-session');
        $this->insertSession($globalTarget, 'authorization-global-session');
        $beforeUsers = $this->securityState([$target, $foreignTarget, $globalTarget]);
        $administratorRoleState = $this->roleState($administrator);
        $protectedRoleState = $this->protectedAdministratorRoleState();
        $auditCount = AuditEvent::query()->count();

        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $tenantActor,
            new TenantContext($context->tenant, $tenantActor),
            $target,
            'tenant-actor-secret',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');
        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $unprotectedGlobal,
            new TenantContext($context->tenant, $unprotectedGlobal),
            $target,
            'unprotected-global-secret',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');
        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $deactivatedAdministrator,
            new TenantContext($context->tenant, $deactivatedAdministrator),
            $target,
            'deactivated-administrator-secret',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');
        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $administrator,
            new TenantContext($context->tenant, $otherAdministrator),
            $target,
            'mismatched-context-secret',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');

        $actorPkSpoof = User::query()->findOrFail($administrator->getKey());
        $actorPkSpoof->forceFill(['id' => $otherAdministrator->getKey()]);
        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $actorPkSpoof,
            $context,
            $target,
            'actor-primary-key-spoof-secret',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');

        $otherTenant = Tenant::factory()->create();
        $contextTenantSpoof = Tenant::query()->findOrFail($context->tenantId);
        $contextTenantSpoof->forceFill(['id' => $otherTenant->getKey()]);
        $this->assertAuthorizationFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $administrator,
            new TenantContext($contextTenantSpoof, $administrator),
            $target,
            'context-primary-key-spoof-secret',
            (string) str()->uuid(),
        ), 'TENANT_CONTEXT_REQUIRED');

        $foreignTarget->forceFill(['tenant_id' => $context->tenantId]);
        $this->assertDomainFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $administrator,
            $context,
            $foreignTarget,
            'foreign-tenant-spoof-secret',
            (string) str()->uuid(),
        ));
        $this->assertDomainFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $administrator,
            $context,
            $globalTarget,
            'global-target-secret',
            (string) str()->uuid(),
        ));

        $otherLocalTarget = User::factory()->create(['tenant_id' => $context->tenantId]);
        $targetPkSpoof = User::query()->findOrFail($otherLocalTarget->getKey());
        $targetPkSpoof->forceFill(['id' => $target->getKey()]);
        $this->assertDomainFailure(fn () => app(ResetTenantUserPassword::class)->execute(
            $administrator,
            $context,
            $targetPkSpoof,
            'target-primary-key-spoof-secret',
            (string) str()->uuid(),
        ));

        $this->assertSame($beforeUsers, $this->securityState([$target, $foreignTarget, $globalTarget]));
        $this->assertSame($administratorRoleState, $this->roleState($administrator));
        $this->assertSame($protectedRoleState, $this->protectedAdministratorRoleState());
        $this->assertDatabaseHas('sessions', ['id' => 'authorization-target-session', 'user_id' => $target->getKey()]);
        $this->assertDatabaseHas('sessions', ['id' => 'authorization-foreign-session', 'user_id' => $foreignTarget->getKey()]);
        $this->assertDatabaseHas('sessions', ['id' => 'authorization-global-session', 'user_id' => $globalTarget->getKey()]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_reset_tenant_user_password_audit_failure_rolls_back_password_sessions_and_audit(): void
    {
        $this->assertTrue(class_exists(ResetTenantUserPassword::class), 'T001-013 must provide ResetTenantUserPassword.');

        [$administrator, $context] = $this->administratorContext();
        $target = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'remember_token' => str()->random(60),
        ]);
        $this->insertSession($target, 'reset-rollback-session');
        $previousHash = (string) $target->password;
        $previousRememberToken = (string) $target->remember_token;
        $secret = 'rollback-audit-secret';
        $correlationId = (string) str()->uuid();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $previousHash, $previousRememberToken, $secret, $target): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $persisted = DB::table('users')->where('id', $target->getKey())->firstOrFail();
            $this->assertNotSame($previousHash, $persisted->password);
            $this->assertTrue(Hash::check($secret, $persisted->password));
            $this->assertNotSame($previousRememberToken, $persisted->remember_token);
            $this->assertDatabaseMissing('sessions', ['id' => 'reset-rollback-session']);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ResetTenantUserPassword::class);
            $action->execute($administrator, $context, $target, $secret, $correlationId);
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('users', [
                'id' => $target->getKey(),
                'password' => $previousHash,
                'remember_token' => $previousRememberToken,
            ]);
            $this->assertDatabaseHas('sessions', ['id' => 'reset-rollback-session', 'user_id' => $target->getKey()]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_reset_session_write_failure_rolls_back_password_token_sessions_and_audit(): void
    {
        $this->assertTrue(class_exists(ResetTenantUserPassword::class), 'T001-013 must provide ResetTenantUserPassword.');

        [$administrator, $context] = $this->administratorContext();
        $target = User::factory()->create([
            'tenant_id' => $context->tenantId,
            'remember_token' => str()->random(60),
        ]);
        $this->insertSession($target, 'reset-write-rollback-session');
        $previousHash = (string) $target->password;
        $previousRememberToken = (string) $target->remember_token;
        $secret = 'rollback-session-write-secret';
        $correlationId = (string) str()->uuid();
        [$dispatcher, $eventName, $listeners] = $this->queryExecutedListeners();

        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) use ($previousHash, $previousRememberToken, $secret, $target): void {
            if (! str_contains(strtolower($event->sql), 'delete from `sessions`')) {
                return;
            }

            $persisted = DB::table('users')->where('id', $target->getKey())->firstOrFail();
            $this->assertNotSame($previousHash, $persisted->password);
            $this->assertTrue(Hash::check($secret, $persisted->password));
            $this->assertNotSame($previousRememberToken, $persisted->remember_token);
            $this->assertDatabaseMissing('sessions', ['id' => 'reset-write-rollback-session']);

            throw new RuntimeException('forced session write failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ResetTenantUserPassword::class);
            $action->execute($administrator, $context, $target, $secret, $correlationId);
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('users', [
                'id' => $target->getKey(),
                'password' => $previousHash,
                'remember_token' => $previousRememberToken,
            ]);
            $this->assertDatabaseHas('sessions', ['id' => 'reset-write-rollback-session', 'user_id' => $target->getKey()]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_invalidate_user_sessions_write_failure_rolls_back_target_sessions_without_password_or_audit_mutation(): void
    {
        $this->assertTrue(class_exists(InvalidateUserSessions::class), 'T001-013 must provide InvalidateUserSessions.');

        $target = User::factory()->create(['remember_token' => str()->random(60)]);
        $this->insertSession($target, 'invalidate-rollback-a');
        $this->insertSession($target, 'invalidate-rollback-b');
        $previousHash = (string) $target->password;
        $previousRememberToken = (string) $target->remember_token;
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->queryExecutedListeners();

        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) use ($previousRememberToken, $target): void {
            if (! str_contains(strtolower($event->sql), 'delete from `sessions`')) {
                return;
            }

            $persisted = DB::table('users')->where('id', $target->getKey())->firstOrFail();
            $this->assertNotSame($previousRememberToken, $persisted->remember_token);
            $this->assertDatabaseMissing('sessions', ['id' => 'invalidate-rollback-a']);
            $this->assertDatabaseMissing('sessions', ['id' => 'invalidate-rollback-b']);

            throw new RuntimeException('forced session write failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(InvalidateUserSessions::class);
            $action->execute($target);
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('users', [
                'id' => $target->getKey(),
                'password' => $previousHash,
                'remember_token' => $previousRememberToken,
            ]);
            $this->assertDatabaseHas('sessions', ['id' => 'invalidate-rollback-a', 'user_id' => $target->getKey()]);
            $this->assertDatabaseHas('sessions', ['id' => 'invalidate-rollback-b', 'user_id' => $target->getKey()]);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['subject_id' => $target->getKey(), 'event_type' => 'tenant.user.password-reset']);
        }
    }

    /** @return array{User, TenantContext} */
    private function administratorContext(): array
    {
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $tenant = Tenant::factory()->create();

        return [$administrator, new TenantContext($tenant, $administrator)];
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

    private function assignRoleDirectly(User $user, int $tenantId, Role $role): void
    {
        DB::table('model_has_roles')->insert([
            'tenant_id' => $tenantId,
            'role_id' => $role->getKey(),
            'model_id' => $user->getKey(),
            'model_type' => $user->getMorphClass(),
        ]);
    }

    private function insertSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '192.0.2.10',
            'user_agent' => 'identity-access-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }

    /** @return array<string, mixed> */
    private function identityState(User $user): array
    {
        return (array) DB::table('users')
            ->where('id', $user->getRawOriginal($user->getKeyName()))
            ->first([
                'id',
                'tenant_id',
                'name',
                'email',
                'email_verified_at',
                'is_active',
                'lock_version',
                'created_at',
            ]);
    }

    /** @return list<array<string, mixed>> */
    private function roleState(User $user): array
    {
        return DB::table('model_has_roles')
            ->where('model_id', $user->getRawOriginal($user->getKeyName()))
            ->where('model_type', $user->getMorphClass())
            ->orderBy('tenant_id')
            ->orderBy('role_id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * @param  list<User>  $users
     * @return array<int, array<string, mixed>>
     */
    private function securityState(array $users): array
    {
        $state = [];

        foreach ($users as $user) {
            $id = (int) $user->getRawOriginal($user->getKeyName());
            $state[$id] = (array) DB::table('users')->where('id', $id)->first([
                'id',
                'tenant_id',
                'password',
                'remember_token',
                'is_active',
                'lock_version',
            ]);
        }

        ksort($state);

        return $state;
    }

    /** @return list<array<string, mixed>> */
    private function tableState(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return array{role: array<string, mixed>, permissions: list<array<string, mixed>>} */
    private function protectedAdministratorRoleState(): array
    {
        $role = DB::table('roles')
            ->whereNull('tenant_id')
            ->where('guard_name', 'web')
            ->where('name', 'Administrator')
            ->firstOrFail();

        return [
            'role' => (array) $role,
            'permissions' => DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->orderBy('permission_id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all(),
        ];
    }

    private function assertAuthorizationFailure(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Password reset unexpectedly bypassed [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    private function assertDomainFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Password reset unexpectedly accepted a target outside the selected tenant.');
        } catch (DomainException $exception) {
            $this->assertSame('TENANT_RELATION_MISMATCH', $exception->getMessage());
        }
    }

    /**
     * @param  list<LogRecord>  $records
     * @param  list<string>  $secrets
     */
    private function assertObservableSecretsAbsent(array $records, array $secrets): void
    {
        foreach ($records as $record) {
            $serialized = json_encode([
                'message' => (string) $record->message,
                'context' => $record->context,
                'extra' => $record->extra,
            ], JSON_THROW_ON_ERROR);

            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $serialized);
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

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function queryExecutedListeners(): array
    {
        $dispatcher = DB::connection()->getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = QueryExecuted::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
    }

    /** @param array<int, mixed> $listeners */
    private function restoreListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);

        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
