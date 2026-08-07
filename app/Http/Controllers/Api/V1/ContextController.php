<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Resources\Api\V1\ContextResource;
use App\Support\Authorization\PermissionCatalogue;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final class ContextController extends Controller
{
    public function show(
        Request $request,
        ResolveTenantContext $resolver,
        PlatformAdministrator $platformAdministrator,
        PermissionRegistrar $registrar,
    ): ContextResource {
        $actor = $this->actor($request);
        $context = $resolver->resolveIfPresent($request);

        if (
            $context instanceof TenantContext
            && $actor->tenant_id !== null
            && $context->tenant->state === TenantState::Inactive
        ) {
            throw new AuthorizationException('TENANT_INACTIVE');
        }
        $previousTeamId = $registrar->getPermissionsTeamId();

        if ($context instanceof TenantContext) {
            $request->attributes->set(TenantContext::class, $context);
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($context->tenantId);
        }

        try {
            $abilities = [];

            foreach (PermissionCatalogue::allAbilities() as $ability) {
                try {
                    $allowed = PermissionCatalogue::isProtected($ability)
                        ? $platformAdministrator->allows($actor, $ability)
                        : $context instanceof TenantContext && (
                            $actor->tenant_id === null
                                ? $platformAdministrator->allows($actor, $ability)
                                : $actor->checkPermissionTo($ability, 'web')
                        );
                } catch (PermissionDoesNotExist) {
                    $allowed = false;
                }

                if ($allowed) {
                    $abilities[] = $ability;
                }
            }

            return ContextResource::make([
                'user' => $actor,
                'platformAdministrator' => $actor->tenant_id === null && $platformAdministrator->hasProtectedRole($actor),
                'tenant' => $context?->tenant,
                'abilities' => $abilities,
            ]);
        } finally {
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
