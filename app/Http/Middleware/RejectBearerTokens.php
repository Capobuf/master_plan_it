<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The private browser API accepts only the Laravel session cookie. Sanctum is
 * retained as the guard so its first-party stateful middleware remains the
 * canonical integration, but bearer/personal tokens are not a browser path.
 */
final class RejectBearerTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            throw new AuthenticationException('AUTHENTICATION_REQUIRED');
        }

        return $next($request);
    }
}
