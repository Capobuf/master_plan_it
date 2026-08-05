<?php

namespace Tests\Feature\IdentityAccess;

use App\Domain\IdentityAccess\Actions\ChangeOwnPassword;
use App\Domain\IdentityAccess\Actions\InvalidateUserSessions;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
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
use Mockery\ExpectationInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChangeOwnPasswordTest extends TestCase
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

    public function test_change_own_password_hashes_once_invalidates_sessions_and_materializes_no_secret_audit(): void
    {
        $this->assertTrue(class_exists(ChangeOwnPassword::class), 'T001-016 must provide ChangeOwnPassword.');
        $this->assertTrue(class_exists(InvalidateUserSessions::class), 'T001-013 must provide InvalidateUserSessions.');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('current-password-fixture'),
            'remember_token' => str()->random(60),
        ]);
        $context = new TenantContext($tenant, $user);
        $this->insertSession($user, 'self-change-session-a');
        $this->insertSession($user, 'self-change-session-b');
        $previousHash = (string) $user->password;
        $previousRememberToken = (string) $user->remember_token;
        $identityBefore = $this->identityState($user);
        $auditCount = AuditEvent::query()->count();
        $correlationId = (string) str()->uuid();

        $realHasher = app('hash');
        $hasherSpy = Mockery::spy($realHasher);
        $hashExpectation = $hasherSpy->shouldReceive('make');
        $this->assertInstanceOf(ExpectationInterface::class, $hashExpectation);
        $hashExpectation->with('new-password-fixture')->once()->passthru();
        Hash::swap($hasherSpy);
        $invalidationSpy = Mockery::spy(app(InvalidateUserSessions::class));
        $invalidationExpectation = $invalidationSpy->shouldReceive('execute');
        $this->assertInstanceOf(ExpectationInterface::class, $invalidationExpectation);
        $invalidationExpectation
            ->withArgs(fn (User $target): bool => $target->is($user))
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
            $action = app(ChangeOwnPassword::class);
            $action->execute($user, $context, 'current-password-fixture', 'new-password-fixture', $correlationId);

            $user->refresh();
            $hashMatches = Hash::check('new-password-fixture', (string) $user->password);
            $logRecords = $logHandler->getRecords();
        } finally {
            $monolog->popHandler();
            Hash::swap($realHasher);
        }

        $this->assertTrue($hashMatches);
        $this->assertNotSame($previousHash, $user->password);
        $this->assertNotSame($previousRememberToken, $user->remember_token);
        $this->assertNotSame('', (string) $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'self-change-session-a']);
        $this->assertDatabaseMissing('sessions', ['id' => 'self-change-session-b']);
        $this->assertSame($identityBefore, $this->identityState($user));
        $this->assertDatabaseCount('audit_events', $auditCount + 1);

        $events = AuditEvent::query()->where('correlation_id', $correlationId)->get();
        $this->assertCount(1, $events);
        $event = $events->sole();
        $this->assertSame('user.password.changed', $event->event_type);
        $this->assertSame($user->getKey(), $event->actor_user_id);
        $this->assertSame($user->name, $event->actor_label);
        $this->assertSame($tenant->getKey(), $event->tenant_id);
        $this->assertSame($user->getMorphClass(), $event->subject_type);
        $this->assertSame($user->getKey(), $event->subject_id);
        $this->assertSame([], $event->properties);

        $serializedUser = $user->toArray();
        $this->assertArrayNotHasKey('password', $serializedUser);
        $this->assertArrayNotHasKey('remember_token', $serializedUser);
        $this->assertObservableSecretsAbsent($logRecords, [
            'current-password-fixture',
            'new-password-fixture',
            $previousHash,
            (string) $user->password,
            $previousRememberToken,
            (string) $user->remember_token,
            'self-change-session-a',
            'self-change-session-b',
        ]);
    }

    public function test_change_own_password_rejects_wrong_current_password_without_side_effects(): void
    {
        $this->assertTrue(class_exists(ChangeOwnPassword::class), 'T001-016 must provide ChangeOwnPassword.');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('correct-current-password'),
            'remember_token' => str()->random(60),
        ]);
        $context = new TenantContext($tenant, $user);
        $this->insertSession($user, 'wrong-current-session');
        $previousHash = (string) $user->password;
        $previousRememberToken = (string) $user->remember_token;
        $auditCount = AuditEvent::query()->count();

        $this->assertDomainFailure(fn () => app(ChangeOwnPassword::class)->execute(
            $user,
            $context,
            'wrong-current-password',
            'new-password-fixture',
            (string) str()->uuid(),
        ));

        $user->refresh();
        $this->assertSame($previousHash, $user->password);
        $this->assertSame($previousRememberToken, $user->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'wrong-current-session', 'user_id' => $user->getKey()]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_change_own_password_rejects_spoofed_actor_and_mismatched_context_without_side_effects(): void
    {
        $this->assertTrue(class_exists(ChangeOwnPassword::class), 'T001-016 must provide ChangeOwnPassword.');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('current-password-fixture'),
            'remember_token' => str()->random(60),
        ]);
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->getKey()]);
        $context = new TenantContext($tenant, $user);
        $this->insertSession($user, 'spoof-actor-session');
        $before = $this->securityState([$user]);
        $auditCount = AuditEvent::query()->count();

        $actorPkSpoof = User::query()->findOrFail($user->getKey());
        $actorPkSpoof->forceFill(['id' => $otherUser->getKey()]);
        $this->assertAuthorizationFailure(fn () => app(ChangeOwnPassword::class)->execute(
            $actorPkSpoof,
            $context,
            'current-password-fixture',
            'new-password-fixture',
            (string) str()->uuid(),
        ), 'PERMISSION_DENIED');

        $contextTenantSpoof = Tenant::query()->findOrFail($tenant->getKey());
        $contextTenantSpoof->forceFill(['id' => $otherTenant->getKey()]);
        $this->assertAuthorizationFailure(fn () => app(ChangeOwnPassword::class)->execute(
            $user,
            new TenantContext($contextTenantSpoof, $user),
            'current-password-fixture',
            'new-password-fixture',
            (string) str()->uuid(),
        ), 'TENANT_CONTEXT_REQUIRED');

        $this->assertSame($before, $this->securityState([$user]));
        $this->assertDatabaseHas('sessions', ['id' => 'spoof-actor-session', 'user_id' => $user->getKey()]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_change_own_password_audit_failure_rolls_back_password_sessions_and_audit(): void
    {
        $this->assertTrue(class_exists(ChangeOwnPassword::class), 'T001-016 must provide ChangeOwnPassword.');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('current-password-fixture'),
            'remember_token' => str()->random(60),
        ]);
        $context = new TenantContext($tenant, $user);
        $this->insertSession($user, 'self-change-rollback-session');
        $previousHash = (string) $user->password;
        $previousRememberToken = (string) $user->remember_token;
        $correlationId = (string) str()->uuid();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId, $previousHash, $previousRememberToken, $user): void {
            if ($event->correlation_id !== $correlationId) {
                return;
            }

            $persisted = DB::table('users')->where('id', $user->getKey())->firstOrFail();
            $this->assertNotSame($previousHash, $persisted->password);
            $this->assertTrue(Hash::check('new-password-fixture', $persisted->password));
            $this->assertNotSame($previousRememberToken, $persisted->remember_token);
            $this->assertDatabaseMissing('sessions', ['id' => 'self-change-rollback-session']);

            throw new RuntimeException('forced audit failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ChangeOwnPassword::class);
            $action->execute($user, $context, 'current-password-fixture', 'new-password-fixture', $correlationId);
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('users', [
                'id' => $user->getKey(),
                'password' => $previousHash,
                'remember_token' => $previousRememberToken,
            ]);
            $this->assertDatabaseHas('sessions', ['id' => 'self-change-rollback-session', 'user_id' => $user->getKey()]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_change_own_password_session_write_failure_rolls_back_password_token_sessions_and_audit(): void
    {
        $this->assertTrue(class_exists(ChangeOwnPassword::class), 'T001-016 must provide ChangeOwnPassword.');

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'password' => Hash::make('current-password-fixture'),
            'remember_token' => str()->random(60),
        ]);
        $context = new TenantContext($tenant, $user);
        $this->insertSession($user, 'self-change-write-rollback-session');
        $previousHash = (string) $user->password;
        $previousRememberToken = (string) $user->remember_token;
        $correlationId = (string) str()->uuid();
        [$dispatcher, $eventName, $listeners] = $this->queryExecutedListeners();

        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $event) use ($previousHash, $previousRememberToken, $user): void {
            if (! str_contains(strtolower($event->sql), 'delete from `sessions`')) {
                return;
            }

            $persisted = DB::table('users')->where('id', $user->getKey())->firstOrFail();
            $this->assertNotSame($previousHash, $persisted->password);
            $this->assertTrue(Hash::check('new-password-fixture', $persisted->password));
            $this->assertNotSame($previousRememberToken, $persisted->remember_token);
            $this->assertDatabaseMissing('sessions', ['id' => 'self-change-write-rollback-session']);

            throw new RuntimeException('forced session write failure');
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ChangeOwnPassword::class);
            $action->execute($user, $context, 'current-password-fixture', 'new-password-fixture', $correlationId);
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('users', [
                'id' => $user->getKey(),
                'password' => $previousHash,
                'remember_token' => $previousRememberToken,
            ]);
            $this->assertDatabaseHas('sessions', ['id' => 'self-change-write-rollback-session', 'user_id' => $user->getKey()]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
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

    private function assertAuthorizationFailure(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Change own password unexpectedly bypassed [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    private function assertDomainFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Change own password unexpectedly accepted an invalid current password.');
        } catch (DomainException $exception) {
            $this->assertSame('CURRENT_PASSWORD_INVALID', $exception->getMessage());
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
