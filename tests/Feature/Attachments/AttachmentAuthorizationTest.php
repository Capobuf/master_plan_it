<?php

namespace Tests\Feature\Attachments;

use App\Domain\Attachments\Services\AttachmentAuthorization;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

class AttachmentAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_policy_combinations_allow_each_supported_same_tenant_parent(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $expense = Expense::factory()->for($tenant)->create();
        $row = ExpenseRow::factory()->for($tenant)->for($expense)->create();

        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => Vendor::factory()->for($tenant)->create()->getKey(),
            'cost_center_id' => CostCenter::factory()->for($tenant)->create()->getKey(),
            'title' => 'Contratto allegati',
            'active' => true,
            'lock_version' => 1,
        ]);

        foreach ([$expense, $row, $contract, Project::factory()->for($tenant)->create()] as $parent) {
            $abilities = app(AttachmentAuthorization::class)->abilities($actor, $context, $parent);
            $this->assertSame(['upload' => true, 'download' => true, 'delete' => true], $abilities);
        }
    }

    public function test_missing_attachment_permission_is_denied_even_with_parent_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $expense = Expense::factory()->for($tenant)->create();
        $this->revokeTenantPermission($actor, $tenant, 'attachment.upload');

        $this->assertAuthorizationCode(
            'PERMISSION_DENIED',
            fn () => app(AttachmentAuthorization::class)->authorizeUpload($actor, new TenantContext($tenant, $actor), $expense),
        );
    }

    public function test_cross_tenant_parent_and_row_fail_without_leakage(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $foreignExpense = Expense::factory()->for($otherTenant)->create();
        $foreignRow = ExpenseRow::factory()->for($otherTenant)->for($foreignExpense)->create();

        foreach ([$foreignExpense, $foreignRow] as $foreignParent) {
            try {
                app(AttachmentAuthorization::class)->authorizeView($actor, $context, $foreignParent);
                $this->fail('A foreign parent was authorized.');
            } catch (ModelNotFoundException $exception) {
                $this->assertNotSame('', $exception->getModel());
            }
        }
    }

    public function test_inactive_actor_and_tenant_are_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $expense = Expense::factory()->for($tenant)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        User::query()->whereKey($actor->getKey())->update(['is_active' => false]);
        $this->assertAuthorizationCode(
            'PERMISSION_DENIED',
            fn () => app(AttachmentAuthorization::class)->authorizeView($actor, new TenantContext($tenant, $actor), $expense),
        );

        User::query()->whereKey($actor->getKey())->update(['is_active' => true]);
        Tenant::query()->whereKey($tenant->getKey())->update(['state' => TenantState::Inactive->value]);
        $this->assertAuthorizationCode(
            'TENANT_INACTIVE',
            fn () => app(AttachmentAuthorization::class)->authorizeView($actor, new TenantContext($tenant, $actor), $expense),
        );
    }

    public function test_unsupported_parent_is_denied_fail_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        $this->assertAuthorizationCode(
            'PERMISSION_DENIED',
            fn () => app(AttachmentAuthorization::class)->authorizeView(
                $actor,
                new TenantContext($tenant, $actor),
                Vendor::factory()->for($tenant)->create(),
            ),
        );
    }

    private function revokeTenantPermission(User $actor, Tenant $tenant, string $permission): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        /** @var Role $role */
        $role = $actor->roles()->where('roles.tenant_id', $tenant->getKey())->firstOrFail();
        $role->revokePermissionTo($permission);
        $registrar->forgetCachedPermissions();
    }

    private function assertAuthorizationCode(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Authorization unexpectedly succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }
}
