<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class SetPermissionTeamContext
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            throw new AuthorizationException('TENANT_CONTEXT_REQUIRED');
        }

        $actor = $context->actor;
        $previousTeamId = $this->registrar->getPermissionsTeamId();

        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $this->registrar->setPermissionsTeamId($context->tenantId);

        try {
            return $next($request);
        } finally {
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $this->registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
