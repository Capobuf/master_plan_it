<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class ApplyTenantPresentationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $previousLocale = App::currentLocale();
        $previousTimezone = date_default_timezone_get();

        try {
            App::setLocale($context->languageCode);
            date_default_timezone_set($context->timezone);

            return $next($request);
        } finally {
            App::setLocale($previousLocale);
            date_default_timezone_set($previousTimezone);
        }
    }
}
