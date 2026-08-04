<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Actions\LeaveTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use DatabaseTransactions;

    private const SESSION_KEY = 'tenant_context.tenant_id';

    public function test_tenant_context_is_readonly_and_carries_the_exact_actor_and_presentation_values(): void
    {
        $tenant = Tenant::factory()->create([
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'currency_code' => 'EUR',
            'default_vat_rate' => '22.125000',
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);

        $context = new TenantContext($tenant, $actor);

        $this->assertTrue((new \ReflectionClass($context))->isReadOnly());
        $this->assertSame($tenant, $context->tenant);
        $this->assertSame($tenant->getKey(), $context->tenantId);
        $this->assertSame($actor, $context->actor);
        $this->assertSame('it', $context->languageCode);
        $this->assertSame('Europe/Rome', $context->timezone);
        $this->assertSame('EUR', $context->currencyCode);
        $this->assertSame('22.125000', $context->defaultVatRate);
    }

    public function test_tenant_user_resolves_only_their_fixed_membership_and_ignores_session_selection(): void
    {
        $ownTenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $ownTenant->getKey()]);
        $request = $this->requestFor($actor);
        $request->session()->put(self::SESSION_KEY, $otherTenant->getKey());

        $response = app(ResolveTenantContext::class)->handle($request, function (Request $handledRequest) use ($actor, $ownTenant) {
            $context = $handledRequest->attributes->get(TenantContext::class);

            $this->assertInstanceOf(TenantContext::class, $context);
            $this->assertSame($ownTenant->getKey(), $context->tenantId);
            $this->assertSame($actor->getKey(), $context->actor->getKey());

            return response('resolved');
        });

        $this->assertSame('resolved', $response->getContent());
        $this->assertSame($otherTenant->getKey(), $request->session()->get(self::SESSION_KEY));
    }

    public function test_administrator_resolves_an_explicit_persisted_session_selection_and_keeps_identity(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $request = $this->requestFor($administrator);
        $request->session()->put(self::SESSION_KEY, $tenant->getKey());

        app(ResolveTenantContext::class)->handle($request, function (Request $handledRequest) use ($administrator, $tenant) {
            $context = $handledRequest->attributes->get(TenantContext::class);

            $this->assertInstanceOf(TenantContext::class, $context);
            $this->assertSame($tenant->getKey(), $context->tenantId);
            $this->assertSame($administrator->getKey(), $context->actor->getKey());
            $this->assertNull($context->actor->tenant_id);

            return response('resolved');
        });
    }

    public function test_missing_or_invalid_administrator_selection_fails_closed_with_stable_code(): void
    {
        $administrator = $this->administrator();

        foreach ([null, '1', -1, PHP_INT_MAX] as $selection) {
            $request = $this->requestFor($administrator);

            if ($selection !== null) {
                $request->session()->put(self::SESSION_KEY, $selection);
            }

            try {
                app(ResolveTenantContext::class)->handle($request, fn () => response('unsafe'));
                $this->fail('Invalid Administrator tenant selection was accepted.');
            } catch (AuthorizationException $exception) {
                $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
                $this->assertFalse($request->attributes->has(TenantContext::class));
            }
        }
    }

    public function test_unauthenticated_tenantless_non_administrator_and_user_without_membership_fail_closed(): void
    {
        $actors = [null, User::factory()->create(['tenant_id' => null]), User::factory()->create(['tenant_id' => null])];

        foreach ($actors as $actor) {
            $request = Request::create('/tenant-context', 'GET');
            $request->setLaravelSession($this->app['session.store']);
            $request->setUserResolver(fn () => $actor);

            try {
                app(ResolveTenantContext::class)->handle($request, fn () => response('unsafe'));
                $this->fail('A request without an authorized tenant identity was accepted.');
            } catch (AuthorizationException $exception) {
                $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
                $this->assertFalse($request->attributes->has(TenantContext::class));
            }
        }
    }

    public function test_inactive_tenant_denies_tenant_user_but_allows_protected_administrator(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $tenantRequest = $this->requestFor($tenantUser);
        $tenantRequest->attributes->set(TenantContext::class, new TenantContext($tenant, $tenantUser));

        try {
            app(EnsureTenantIsActive::class)->handle($tenantRequest, fn () => response('unsafe'));
            $this->fail('Inactive tenant user was allowed.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_INACTIVE', $exception->getMessage());
        }

        $administrator = $this->administrator();
        $administratorRequest = $this->requestFor($administrator);
        $administratorRequest->attributes->set(TenantContext::class, new TenantContext($tenant, $administrator));

        $response = app(EnsureTenantIsActive::class)->handle(
            $administratorRequest,
            fn () => response('administrator-retained-access'),
        );

        $this->assertSame('administrator-retained-access', $response->getContent());
    }

    public function test_enter_and_leave_actions_audit_real_administrator_and_selected_tenant_before_session_change(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $session = $this->app['session.store'];
        $enterCorrelation = (string) str()->uuid();
        $leaveCorrelation = (string) str()->uuid();

        $entered = app(EnterTenantContext::class)->execute(
            $administrator,
            $tenant,
            $session,
            $enterCorrelation,
        );

        $this->assertSame($tenant->getKey(), $entered->tenantId);
        $this->assertSame($administrator->getKey(), $entered->actor->getKey());
        $this->assertSame($tenant->getKey(), $session->get(self::SESSION_KEY));
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.context.entered',
            'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->getKey(),
            'correlation_id' => $enterCorrelation,
        ]);

        app(LeaveTenantContext::class)->execute($administrator, $session, $leaveCorrelation);

        $this->assertFalse($session->has(self::SESSION_KEY));
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $administrator->getKey(),
            'event_type' => 'tenant.context.left',
            'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->getKey(),
            'correlation_id' => $leaveCorrelation,
        ]);
    }

    public function test_context_actions_reject_tenant_users_without_changing_selection(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantUser = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $session = $this->app['session.store'];
        $session->put(self::SESSION_KEY, 731);

        try {
            app(EnterTenantContext::class)->execute($tenantUser, $tenant, $session, 'denied-enter');
            $this->fail('Tenant user entered Administrator tenant context.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('PERMISSION_DENIED', $exception->getMessage());
            $this->assertSame(731, $session->get(self::SESSION_KEY));
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => 'denied-enter']);
        }

        $session->put(self::SESSION_KEY, $tenant->getKey());

        try {
            app(LeaveTenantContext::class)->execute($tenantUser, $session, 'denied-leave');
            $this->fail('Tenant user left Administrator tenant context.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('PERMISSION_DENIED', $exception->getMessage());
            $this->assertSame($tenant->getKey(), $session->get(self::SESSION_KEY));
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => 'denied-leave']);
        }
    }

    public function test_context_actions_require_a_persisted_explicit_target_or_selection(): void
    {
        $administrator = $this->administrator();
        $session = $this->app['session.store'];
        $session->put(self::SESSION_KEY, 811);

        try {
            app(EnterTenantContext::class)->execute(
                $administrator,
                new Tenant(['name' => 'Unpersisted']),
                $session,
                'invalid-enter',
            );
            $this->fail('An unpersisted tenant target was accepted.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
            $this->assertSame(811, $session->get(self::SESSION_KEY));
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => 'invalid-enter']);
        }

        $session->forget(self::SESSION_KEY);

        try {
            app(LeaveTenantContext::class)->execute($administrator, $session, 'invalid-leave');
            $this->fail('Leave accepted a missing explicit selection.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
            $this->assertFalse($session->has(self::SESSION_KEY));
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => 'invalid-leave']);
        }
    }

    public function test_active_state_middleware_requires_the_resolved_request_context(): void
    {
        try {
            app(EnsureTenantIsActive::class)->handle(Request::create('/missing-context'), fn () => response('unsafe'));
            $this->fail('Active-state middleware accepted a missing TenantContext.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_native_authorization_exceptions_render_stable_codes_as_http_403(): void
    {
        Route::get('/api/_contract/tenant-authorization/{code}', function (string $code): never {
            throw new AuthorizationException($code);
        });

        foreach (['TENANT_CONTEXT_REQUIRED', 'TENANT_INACTIVE', 'PERMISSION_DENIED'] as $code) {
            $this->getJson('/api/_contract/tenant-authorization/'.$code)
                ->assertForbidden()
                ->assertJsonPath('message', $code);
        }
    }

    public function test_enter_context_audit_failure_rolls_back_database_and_preserves_session(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $session = $this->app['session.store'];
        $session->put(self::SESSION_KEY, 409);
        $correlationId = (string) str()->uuid();
        $beforeCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(EnterTenantContext::class);
            $action->execute($administrator, $tenant, $session, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame(409, $session->get(self::SESSION_KEY));
            $this->assertDatabaseCount('audit_events', $beforeCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_leave_context_audit_failure_rolls_back_database_and_preserves_session(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $session = $this->app['session.store'];
        $session->put(self::SESSION_KEY, $tenant->getKey());
        $correlationId = (string) str()->uuid();
        $beforeCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $originalListeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(LeaveTenantContext::class);
            $action->execute($administrator, $session, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $originalListeners);
            $this->assertSame($tenant->getKey(), $session->get(self::SESSION_KEY));
            $this->assertDatabaseCount('audit_events', $beforeCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    private function administrator(): User
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    private function requestFor(User $actor): Request
    {
        $request = Request::create('/tenant-context', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $actor);

        return $request;
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
