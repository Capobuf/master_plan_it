<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->exists) {
            throw new AuthenticationException('AUTHENTICATION_REQUIRED');
        }

        $persistedActor = User::query()->find($actor->getKey());

        if (! $persistedActor instanceof User) {
            throw new AuthenticationException('AUTHENTICATION_REQUIRED');
        }

        if (! $persistedActor->is_active) {
            throw new AuthorizationException('ACCOUNT_INACTIVE');
        }

        Auth::setUser($persistedActor);
        $request->setUserResolver(fn (): User => $persistedActor);

        return $next($request);
    }
}
