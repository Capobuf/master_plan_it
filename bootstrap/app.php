<?php

use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Support\Diagnostics\CorrelationId;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignCorrelationId::class);
        $middleware->alias([
            'active-user' => EnsureActiveUser::class,
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
