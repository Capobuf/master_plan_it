<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeApplicationAbility
{
    public function __construct(
        private readonly PlatformAdministrator $platformAdministrator,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User || ! $this->allows($request, $actor, $ability)) {
            throw new AuthorizationException('PERMISSION_DENIED');
        }

        return $next($request);
    }

    public function allows(Request $request, User $actor, string $ability): bool
    {
        if (PermissionCatalogue::isProtected($ability)) {
            return $this->platformAdministrator->allows($actor, $ability);
        }

        if (! PermissionCatalogue::isTenant($ability)) {
            return false;
        }

        $context = $request->attributes->get(TenantContext::class);

        if (! $context instanceof TenantContext) {
            return false;
        }

        if ($actor->tenant_id === null) {
            return $this->platformAdministrator->allows($actor, $ability);
        }

        if (
            (int) $actor->tenant_id !== $context->tenantId
            || $this->permissionRegistrar->getPermissionsTeamId() !== $context->tenantId
        ) {
            return false;
        }

        try {
            return $actor->checkPermissionTo($ability, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
