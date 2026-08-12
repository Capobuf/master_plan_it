<?php

namespace App\Domain\Plafonds\Services;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Spatie\Permission\PermissionRegistrar;

final class PlafondMutationAuthorizer
{
    public function authorize(User $actor, TenantContext $context): void
    {
        (new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        ))->manageBudget($actor)->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
    }
}
