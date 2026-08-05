<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOptionalTenantContext
{
    public function __construct(
        private readonly ResolveTenantContext $resolver,
        private readonly SetPermissionTeamContext $permissionTeamContext,
        private readonly EnsureTenantIsActive $activeTenant,
        private readonly ApplyTenantPresentationContext $presentationContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get(TenantContext::class) instanceof TenantContext) {
            return $next($request);
        }

        $context = $this->resolver->resolveIfPresent($request);

        if (! $context instanceof TenantContext) {
            return $next($request);
        }

        $request->attributes->set(TenantContext::class, $context);

        return $this->permissionTeamContext->handle(
            $request,
            fn (Request $request): Response => $this->activeTenant->handle(
                $request,
                fn (Request $request): Response => $this->presentationContext->handle($request, $next),
            ),
        );
    }
}
