<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(private readonly PlatformAdministrator $platformAdministrator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->resolveIfPresent($request);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $request->attributes->set(TenantContext::class, $context);

        return $next($request);
    }

    public function resolveIfPresent(Request $request): ?TenantContext
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $actor->exists) {
            return null;
        }

        $tenant = $actor->tenant_id === null
            ? $this->resolveAdministratorSelection($request, $actor)
            : Tenant::query()->find((int) $actor->tenant_id);

        return $tenant instanceof Tenant
            ? new TenantContext($tenant, $actor)
            : null;
    }

    private function resolveAdministratorSelection(Request $request, User $actor): ?Tenant
    {
        if (! $this->platformAdministrator->hasProtectedRole($actor) || ! $request->hasSession()) {
            return null;
        }

        $selectedTenantId = $request->session()->get(EnterTenantContext::SESSION_KEY);

        if (! is_int($selectedTenantId) || $selectedTenantId < 1) {
            return null;
        }

        return Tenant::query()->find($selectedTenantId);
    }
}
