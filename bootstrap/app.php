<?php

use App\Console\Commands\ApplyOperationalRevisionRetentionCommand;
use App\Console\Commands\PromoteDeferredProjectsCommand;
use App\Console\Commands\TestResetGreenfield;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use App\Models\Version;
use App\Support\Api\ApiErrorResponse;
use App\Support\Diagnostics\CorrelationId;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        ApplyOperationalRevisionRetentionCommand::class,
        PromoteDeferredProjectsCommand::class,
        TestResetGreenfield::class,
    ])
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->append(AssignCorrelationId::class);
        $middleware->alias([
            'active-user' => EnsureActiveUser::class,
            'application-ability' => AuthorizeApplicationAbility::class,
            'tenant-context' => ResolveTenantContext::class,
            'permission-team-context' => SetPermissionTeamContext::class,
            'active-tenant' => EnsureTenantIsActive::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureActiveUser::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenantContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetPermissionTeamContext::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureTenantIsActive::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('projects:promote-deferred')->dailyAt('00:15')->withoutOverlapping();
        $schedule->command('revisions:apply-retention')->dailyAt('00:30')->withoutOverlapping();
        $schedule->command('model:prune', ['--model' => Version::class])->dailyAt('00:45')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiErrorResponse::from($exception, $request);
            }

            return null;
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::from($exception, $request);
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
