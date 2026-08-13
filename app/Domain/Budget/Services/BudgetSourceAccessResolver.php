<?php

namespace App\Domain\Budget\Services;

use App\Domain\Budget\Data\BudgetSourceAccess;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

final readonly class BudgetSourceAccessResolver
{
    public function __construct(
        private PermissionRegistrar $registrar,
        private PlatformAdministrator $administrator,
    ) {}

    public function resolve(User $actor, TenantContext $context): BudgetSourceAccess
    {
        $abilities = [
            'expense' => 'expense.view',
            'planningYear' => 'planning-year.view',
            'costCenter' => 'cost-center.view',
            'vendor' => 'vendor.view',
            'project' => 'project.view',
            'contract' => 'contract.view',
            'expenseUpdate' => 'expense.update',
        ];
        $allowed = array_fill_keys(array_keys($abilities), false);
        $teamId = $actor->tenant_id === null ? PlatformAdministrator::PLATFORM_TEAM_ID : $context->tenantId;
        $previous = $this->registrar->getPermissionsTeamId();
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $this->registrar->setPermissionsTeamId($teamId);

        try {
            if ($actor->tenant_id !== null || $this->administrator->hasProtectedRole($actor)) {
                foreach ($abilities as $key => $ability) {
                    $allowed[$key] = $actor->checkPermissionTo($ability, 'web');
                }
            }
        } catch (PermissionDoesNotExist) {
            // The complete decision remains fail-closed.
        } finally {
            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
            $this->registrar->setPermissionsTeamId($previous);
        }

        return new BudgetSourceAccess(...$allowed);
    }
}
