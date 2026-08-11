<?php

namespace Tests\Support;

use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;

trait InteractsWithAttachments
{
    use InteractsWithApiFoundation;

    /** @return array{0: Tenant, 1: User, 2: TenantContext} */
    protected function attachmentContext(?string $quotaBytes = null): array
    {
        $tenant = Tenant::factory()->create($quotaBytes === null ? [] : ['attachment_quota_bytes' => $quotaBytes]);
        $actor = $this->tenantUser($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    /** @return array{expense: Expense, row: ExpenseRow, contract: Contract, project: Project} */
    protected function supportedAttachmentParents(Tenant $tenant): array
    {
        $expense = Expense::factory()->for($tenant)->create();
        $row = ExpenseRow::factory()->for($tenant)->for($expense)->create();
        $costCenter = CostCenter::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => Vendor::factory()->for($tenant)->create()->getKey(),
            'cost_center_id' => $costCenter->getKey(),
            'title' => 'Contratto allegati',
            'active' => true,
            'lock_version' => 1,
        ]);
        $project = Project::factory()->for($tenant)->for($costCenter)->create();

        return compact('expense', 'row', 'contract', 'project');
    }

    protected function revokeAttachmentAbility(User $actor, Tenant $tenant, string $permission): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        /** @var Role $role */
        $role = $actor->roles()->where('roles.tenant_id', $tenant->getKey())->firstOrFail();
        $role->revokePermissionTo($permission);
        $registrar->forgetCachedPermissions();
    }

    protected function resetAttachmentPermissionScope(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
