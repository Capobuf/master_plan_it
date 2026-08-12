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
        $policy = $this->policy($context);
        ($expense instanceof Expense ? $policy->view($actor, $expense) : $policy->viewAny($actor))
            ->authorize();
        app(PlafondRelationshipAuthorizer::class)->authorize($actor, $context);
    }

    public function canViewAny(User $actor, TenantContext $context): bool
    {
        return $this->policy($context)->viewAny($actor)->allowed();
    }

    public function canViewExpense(User $actor, TenantContext $context, Expense $expense): bool
    {
        return $this->policy($context)->view($actor, $expense)->allowed();
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }
}
