<?php

use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/operational.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request): string => route('login'),
        );
        $middleware->redirectUsersTo(
            fn (Request $request): string => route('home'),
        );

        $middleware->append(AssignCorrelationId::class);
        $middleware->appendToGroup('web', HandleInertiaRequests::class);
        $middleware->alias([
            'active-user' => EnsureActiveUser::class,
            'application-ability' => AuthorizeApplicationAbility::class,
            'tenant-context' => ResolveTenantContext::class,
            'permission-team-context' => SetPermissionTeamContext::class,
            'active-tenant' => EnsureTenantIsActive::class,
            'tenant-presentation' => ApplyTenantPresentationContext::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureActiveUser::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenantContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetPermissionTeamContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantIsActive::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ApplyTenantPresentationContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            $actor = $request->user();

            if (
                $exception->getMessage() === 'TENANT_CONTEXT_REQUIRED'
                && ! $request->expectsJson()
                && $actor instanceof User
                && app(PlatformAdministrator::class)->hasProtectedRole($actor)
            ) {
                return redirect()->route('platform.tenants.index');
            }

            return null;
        });

        $exceptions->render(function (DomainException $exception, Request $request) {
            $code = $exception->getMessage();

            if ($code === 'CURRENT_PASSWORD_INVALID') {
                return back()->withErrors([
                    'current_password' => 'The current password is incorrect.',
                ]);
            }

            if ($code === 'DESTRUCTIVE_CONFIRMATION_REQUIRED') {
                return back()->withErrors([
                    'confirmation_code' => 'Enter the exact tenant code to confirm deactivation.',
                ]);
            }

            $message = match ($code) {
                'STALE_VERSION' => 'This record changed after the page was loaded. Refresh and try again.',
                'REFERENCED_RECORD_DELETE_DENIED' => 'This record is referenced and cannot be deleted.',
                'TENANT_ROLE_IN_USE' => 'This role cannot be deleted while it is the only role assigned to a user.',
                'COST_CENTER_CYCLE' => 'The selected parent would create a cost center cycle.',
                'COST_CENTER_DEPTH_EXCEEDED' => 'Cost centers may have at most three hierarchy levels.',
                'ACTIVE_DESCENDANT_EXISTS' => 'Deactivate active descendants before deactivating this cost center.',
                'PLATFORM_ABILITY_PROTECTED' => 'Platform abilities cannot be assigned to tenant roles.',
                'TENANT_RELATION_MISMATCH', 'REVISION_RESTORE_INVALID' => 'The requested change conflicts with the current tenant data.',
                default => null,
            };

            return $message === null ? null : back()->with('error', $message);
        });

        $exceptions->context(function (): array {
            if (! app()->bound('request')) {
                return [];
            }

            return [
                CorrelationId::LOG_CONTEXT_KEY => CorrelationId::resolveFor(request())->value(),
            ];
        });

        $exceptions->respond(function (Response $response, Throwable $_exception, Request $request): Response {
            $correlationId = CorrelationId::resolveFor($request);
            $response->headers->set(CorrelationId::HEADER, $correlationId->value());

            return $response;
        });
    })->create();
