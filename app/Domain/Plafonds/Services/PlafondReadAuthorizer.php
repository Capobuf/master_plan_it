<?php

namespace App\Domain\Plafonds\Services;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Spatie\Permission\PermissionRegistrar;

final class PlafondReadAuthorizer
{
    public function authorize(User $actor, TenantContext $context, ?Expense $expense = null): void
    {
        $policy = new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
        ($expense instanceof Expense ? $policy->view($actor, $expense) : $policy->viewAny($actor))
            ->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
    }
}
