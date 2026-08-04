<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use App\Support\Diagnostics\CorrelationId;
use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    private const string PREVIOUS_LOG_CONTEXT_ATTRIBUTE = self::class.'.previous_log_context';

    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = CorrelationId::resolveFor($request);
        $previousLogContext = Log::sharedContext();
        unset($previousLogContext[CorrelationId::LOG_CONTEXT_KEY]);

        Log::withoutContext([CorrelationId::LOG_CONTEXT_KEY]);
        Log::flushSharedContext();
        Log::shareContext($previousLogContext);

        $request->attributes->set(self::PREVIOUS_LOG_CONTEXT_ATTRIBUTE, $previousLogContext);
        Log::shareContext([CorrelationId::LOG_CONTEXT_KEY => $correlationId->value()]);

        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId->value());

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->has(self::PREVIOUS_LOG_CONTEXT_ATTRIBUTE)) {
            $previousLogContext = $request->attributes->get(self::PREVIOUS_LOG_CONTEXT_ATTRIBUTE, []);

            Log::withoutContext();
            Log::flushSharedContext();

            if (is_array($previousLogContext) && $previousLogContext !== []) {
                Log::shareContext($previousLogContext);
            }
        } else {
            $sharedContext = Log::sharedContext();
            unset($sharedContext[CorrelationId::LOG_CONTEXT_KEY]);

            Log::withoutContext([CorrelationId::LOG_CONTEXT_KEY]);
            Log::flushSharedContext();
            Log::shareContext($sharedContext);
        }

        $request->attributes->remove(CorrelationId::class);
        $request->attributes->remove(TenantContext::class);
        $request->attributes->remove(self::PREVIOUS_LOG_CONTEXT_ATTRIBUTE);
        $this->app->forgetInstance(CorrelationId::class);
        $this->app->forgetInstance(TenantContext::class);
    }
}
