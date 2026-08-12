<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\User;
use App\Policies\ContractPolicy;
use App\Policies\CostCenterPolicy;
use App\Policies\PlanningYearPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\VendorPolicy;
use App\Support\Authorization\PlatformAdministrator;
use Spatie\Permission\PermissionRegistrar;

final class ExpenseRelationshipAuthorizer
{
    public function authorize(
        User $actor,
        TenantContext $context,
        bool $exposesProject = false,
        bool $exposesContract = false,
    ): void {
        $registrar = app(PermissionRegistrar::class);
        $administrator = app(PlatformAdministrator::class);

        (new PlanningYearPolicy($context, $registrar, $administrator))->viewAny($actor)->authorize();
        (new CostCenterPolicy($context, $registrar, $administrator))->viewAny($actor)->authorize();
        (new VendorPolicy($context, $registrar, $administrator))->viewAny($actor)->authorize();

        if ($exposesProject) {
            (new ProjectPolicy($context, $registrar, $administrator))->viewAny($actor)->authorize();
        }
        if ($exposesContract) {
            (new ContractPolicy($context, $registrar, $administrator))->viewAny($actor)->authorize();
        }
    }
}
