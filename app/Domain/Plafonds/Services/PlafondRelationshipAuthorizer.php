<?php

namespace App\Domain\Plafonds\Services;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\CostCenterPolicy;
use App\Policies\PlanningYearPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Spatie\Permission\PermissionRegistrar;

final class PlafondRelationshipAuthorizer
{
    public function authorize(User $actor, TenantContext $context): void
    {
        $registrar = app(PermissionRegistrar::class);
        $administrator = app(PlatformAdministrator::class);

        (new PlanningYearPolicy($context, $registrar, $administrator))
            ->viewAny($actor)
            ->authorize();
        (new CostCenterPolicy($context, $registrar, $administrator))
            ->viewAny($actor)
            ->authorize();
    }
}
