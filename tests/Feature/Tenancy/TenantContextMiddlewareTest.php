<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantContextMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'logging.default' => 'null',
        ]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        parent::tearDown();
    }

    public function test_scoped_tenant_context_is_the_exact_request_attribute_and_missing_attribute_fails_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $context = new TenantContext($tenant, $actor);
        $request = Request::create('/context-binding');
        $request->attributes->set(TenantContext::class, $context);
        $this->app->instance('request', $request);
        $this->app->forgetScopedInstances();

        $this->assertSame($context, app(TenantContext::class));
        $this->assertSame(app(TenantContext::class), app(TenantContext::class));

        $missingRequest = Request::create('/missing-context-binding');
        $this->app->instance('request', $missingRequest);
        $this->app->forgetScopedInstances();

        try {
            app(TenantContext::class);
            $this->fail('TenantContext was resolved without its validated request attribute.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
        }
    }

    public function test_active_user_middleware_denies_missing_unpersisted_and_inactive_actors_with_stable_codes(): void
    {
        $missingRequest = Request::create('/active-user');
        $missingRequest->setUserResolver(fn () => null);

        foreach ([$missingRequest, $this->requestFor(new User)] as $request) {
            try {
                app(EnsureActiveUser::class)->handle($request, fn () => response('unsafe'));
                $this->fail('A missing or unpersisted actor passed active-user validation.');
            } catch (AuthenticationException $exception) {
                $this->assertSame('AUTHENTICATION_REQUIRED', $exception->getMessage());
            }
        }

        $inactive = User::factory()->inactive()->create();

        try {
            app(EnsureActiveUser::class)->handle($this->requestFor($inactive), fn () => response('unsafe'));
            $this->fail('An inactive actor passed active-user validation.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('ACCOUNT_INACTIVE', $exception->getMessage());
        }
    }

    public function test_route_binding_observes_active_actor_tenant_team_and_presentation_context_in_exact_order(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $tenant = Tenant::factory()->create([
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $binderCalled = false;

        Route::bind('tenant_probe', function (string $value) use ($actor, $tenant, $registrar, &$binderCalled): string {
            $binderCalled = true;
            $context = request()->attributes->get(TenantContext::class);

            $this->assertInstanceOf(TenantContext::class, $context);
            $this->assertSame($context, app(TenantContext::class));
            $this->assertSame($actor->getKey(), $context->actor->getKey());
            $this->assertTrue($context->actor->is_active);
            $this->assertSame($tenant->getKey(), $context->tenantId);
            $this->assertSame($tenant->getKey(), $registrar->getPermissionsTeamId());
            $this->assertSame('it', App::currentLocale());
            $this->assertSame('Europe/Rome', date_default_timezone_get());

            return $value;
        });

        $this->registerContextRoute('/api/_contract/context-order/{tenant_probe}');

        $this->actingAs($actor)
            ->getJson('/api/_contract/context-order/observed')
            ->assertOk()
            ->assertJsonPath('probe', 'observed')
            ->assertJsonPath('tenant_id', $tenant->getKey());

        $this->assertTrue($binderCalled);
        $this->assertNull($registrar->getPermissionsTeamId());
        $this->assertSame($originalLocale, App::currentLocale());
        $this->assertSame($originalTimezone, date_default_timezone_get());
    }

    public function test_aliases_and_priority_place_the_context_chain_between_authentication_and_route_binding(): void
    {
        app(Kernel::class);
        $aliases = $this->app['router']->getMiddleware();

        $this->assertSame(EnsureActiveUser::class, $aliases['active-user'] ?? null);
        $this->assertSame(ResolveTenantContext::class, $aliases['tenant-context'] ?? null);
        $this->assertSame(SetPermissionTeamContext::class, $aliases['permission-team-context'] ?? null);
        $this->assertSame(EnsureTenantIsActive::class, $aliases['active-tenant'] ?? null);
        $this->assertSame(ApplyTenantPresentationContext::class, $aliases['tenant-presentation'] ?? null);

        $priority = app(Kernel::class)->getMiddlewarePriority();
        $ordered = [
            EnsureActiveUser::class,
            ResolveTenantContext::class,
            SetPermissionTeamContext::class,
            EnsureTenantIsActive::class,
            ApplyTenantPresentationContext::class,
            SubstituteBindings::class,
            Authorize::class,
        ];
        $indexes = array_map(
            fn (string $middleware): int|false => array_search($middleware, $priority, true),
            $ordered,
        );

        foreach ($indexes as $index) {
            $this->assertIsInt($index);
        }

        $authenticationIndex = array_search(AuthenticatesRequests::class, $priority, true);

        $this->assertIsInt($authenticationIndex);
        $this->assertSame($indexes, collect($indexes)->sort()->values()->all());
        $this->assertLessThan($indexes[0], $authenticationIndex);
    }

    public function test_inactive_user_is_denied_before_tenant_resolution_and_invalid_administrator_session_fails_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $inactive = User::factory()->inactive()->create(['tenant_id' => $tenant->getKey()]);
        $this->registerContextRoute('/api/_contract/context-denial/{tenant_probe}');

        $this->actingAs($inactive)
            ->getJson('/api/_contract/context-denial/inactive')
            ->assertForbidden()
            ->assertJsonPath('message', 'ACCOUNT_INACTIVE');

        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->withSession([EnterTenantContext::SESSION_KEY => 'invalid'])
            ->getJson('/api/_contract/context-denial/invalid-session')
            ->assertForbidden()
            ->assertJsonPath('message', 'TENANT_CONTEXT_REQUIRED');
    }

    public function test_consecutive_http_requests_do_not_leak_tenant_team_locale_timezone_or_scoped_context(): void
    {
        $originalLocale = App::currentLocale();
        $originalTimezone = date_default_timezone_get();
        $registrar = app(PermissionRegistrar::class);
        $tenantA = Tenant::factory()->create(['language_code' => 'it', 'timezone' => 'Europe/Rome']);
        $tenantB = Tenant::factory()->create(['language_code' => 'en', 'timezone' => 'UTC']);
        $actorA = User::factory()->create(['tenant_id' => $tenantA->getKey()]);
        $actorB = User::factory()->create(['tenant_id' => $tenantB->getKey()]);

        Route::bind('tenant_probe', function (string $value): string {
            $context = app(TenantContext::class);

            $this->assertSame($context->tenantId, app(PermissionRegistrar::class)->getPermissionsTeamId());
            $this->assertSame($context->languageCode, App::currentLocale());
            $this->assertSame($context->timezone, date_default_timezone_get());

            return $value;
        });
        $this->registerContextRoute('/api/_contract/context-isolation/{tenant_probe}');

        foreach ([[$actorA, $tenantA], [$actorB, $tenantB]] as [$actor, $tenant]) {
            $this->actingAs($actor)
                ->getJson('/api/_contract/context-isolation/request')
                ->assertOk()
                ->assertJson([
                    'tenant_id' => $tenant->getKey(),
                    'locale' => $tenant->language_code,
                    'timezone' => $tenant->timezone,
                    'team_id' => $tenant->getKey(),
                ]);

            $this->assertNull($registrar->getPermissionsTeamId());
            $this->assertSame($originalLocale, App::currentLocale());
            $this->assertSame($originalTimezone, date_default_timezone_get());

            try {
                app(TenantContext::class);
                $this->fail('A terminated request retained its scoped TenantContext.');
            } catch (AuthorizationException $exception) {
                $this->assertSame('TENANT_CONTEXT_REQUIRED', $exception->getMessage());
            }
        }
    }

    private function registerContextRoute(string $uri): void
    {
        Route::get($uri, function (string $tenant_probe) {
            $context = app(TenantContext::class);

            return response()->json([
                'probe' => $tenant_probe,
                'tenant_id' => $context->tenantId,
                'locale' => App::currentLocale(),
                'timezone' => date_default_timezone_get(),
                'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
            ]);
        })->middleware([
            'web',
            'tenant-presentation',
            'active-tenant',
            'permission-team-context',
            'tenant-context',
            'active-user',
            'auth',
        ]);
    }

    private function administrator(): User
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);

        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);

        return $administrator;
    }

    private function requestFor(?User $actor): Request
    {
        $request = Request::create('/active-user');
        $request->setUserResolver(fn () => $actor);

        return $request;
    }
}
