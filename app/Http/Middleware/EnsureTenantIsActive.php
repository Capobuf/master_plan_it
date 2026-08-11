<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Support\Authorization\PlatformAdministrator;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsActive
{
    public function __construct(private readonly PlatformAdministrator $platformAdministrator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        if (
            $context->tenant->state !== TenantState::Active
            && ! $this->platformAdministrator->hasProtectedRole($context->actor)
        ) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }

        return $next($request);
    }
}
