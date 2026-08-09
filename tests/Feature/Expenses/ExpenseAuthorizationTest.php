<?php

namespace Tests\Feature\Expenses;

use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_each_expense_operation_requires_its_exact_same_tenant_ability(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create();
        $policy = $this->policy(new TenantContext($tenant, $actor));

        foreach ([
            ['viewAny', ['expense.view'], null],
            ['view', ['expense.view'], $expense],
            ['create', ['expense.create'], null],
            ['update', ['expense.update'], $expense],
            ['delete', ['expense.delete'], $expense],
            ['restoreRevision', ['expense.restore-revision'], $expense],
            ['viewRevisions', ['expense.view-revisions'], $expense],
            ['viewAttachment', ['expense.view', 'attachment.view'], $expense],
            ['uploadAttachment', ['expense.update', 'attachment.upload'], $expense],
            ['deleteAttachment', ['expense.delete', 'attachment.delete'], $expense],
            ['viewRevisionAttachment', ['expense.view-revisions', 'attachment.view'], $expense],
            ['print', ['expense.print'], $expense],
            ['export', ['expense.export'], $expense],
        ] as [$method, $abilities, $resource]) {
            $roleName = 'Exact '.$method;
            $this->grant($actor, $tenant, $abilities, $roleName);

            $response = $resource === null
                ? $policy->{$method}($actor)
                : $policy->{$method}($actor, $resource);

            $this->assertTrue($response->allowed(), "{$method} did not allow its exact ability set.");

            $this->revoke($actor, $tenant, $roleName, $abilities[0]);
            $response = $resource === null
                ? $policy->{$method}($actor)
                : $policy->{$method}($actor, $resource);

            $this->assertDenied($response, 'PERMISSION_DENIED');
        }
    }

    public function test_expense_policy_fails_closed_for_missing_inactive_foreign_and_missing_resources(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create();
        $foreignExpense = Expense::factory()->for($otherTenant)->create();
        $policy = $this->policy(new TenantContext($tenant, $actor));

        $this->assertDenied($policy->view($actor, $expense), 'PERMISSION_DENIED');

        $this->grant($actor, $tenant, ['expense.view'], 'Expense reader');
        User::query()->whereKey($actor->getKey())->update(['is_active' => false]);
        $this->assertDenied($policy->view($actor, $expense), 'PERMISSION_DENIED');

        User::query()->whereKey($actor->getKey())->update(['is_active' => true]);
        $this->assertNotFound($policy->view($actor, $foreignExpense));

        $otherActor = User::factory()->for($otherTenant)->create();
        $this->grant($otherActor, $otherTenant, ['expense.view'], 'Other tenant expense reader');
        $this->assertDenied($policy->view($otherActor, $expense), 'PERMISSION_DENIED');

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $missingExpense = new Expense;
        $missingExpense->forceFill(['id' => 999999, 'tenant_id' => $tenant->getKey()]);
        $this->assertNotFound($policy->view($actor, $missingExpense));
    }

    public function test_persisted_inactive_selected_tenant_denies_collection_and_record_without_changing_team_scope(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Active]);
        $actor = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create();
        $context = new TenantContext($tenant, $actor);
        $this->grant($actor, $tenant, ['expense.view'], 'Expense reader');
        $registrar = app(PermissionRegistrar::class);
        $selectedTeamId = $registrar->getPermissionsTeamId();

        Tenant::query()->whereKey($tenant->getKey())->update(['state' => TenantState::Inactive->value]);

        $policy = $this->policy($context);
        $this->assertDenied($policy->viewAny($actor), 'TENANT_INACTIVE');
        $this->assertSame($selectedTeamId, $registrar->getPermissionsTeamId());
        $this->assertDenied($policy->view($actor, $expense), 'TENANT_INACTIVE');
        $this->assertSame($selectedTeamId, $registrar->getPermissionsTeamId());
    }

    public function test_expense_policy_rejects_spoofed_actors_and_invalid_tenant_contexts(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create();
        $this->grant($actor, $tenant, ['expense.view'], 'Expense reader');

        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $spoofedActor = $actor->replicate();
        $spoofedActor->forceFill([$spoofedActor->getKeyName() => $administrator->getKey()]);

        $this->assertDenied(
            $this->policy(new TenantContext($tenant, $spoofedActor))->view($spoofedActor, $expense),
            'PERMISSION_DENIED',
        );

        $otherActor = User::factory()->for($tenant)->create();
        $this->assertDenied(
            $this->policy(new TenantContext($tenant, $otherActor))->view($actor, $expense),
            'PERMISSION_DENIED',
        );

        $forgedTenant = new Tenant;
        $forgedTenant->forceFill($tenant->getAttributes());
        $this->assertDenied(
            $this->policy(new TenantContext($forgedTenant, $actor))->view($actor, $expense),
            'TENANT_CONTEXT_REQUIRED',
        );
    }

    public function test_role_names_and_global_administrator_status_do_not_bypass_exact_abilities(): void
    {
        $tenant = Tenant::factory()->create();
        $expense = Expense::factory()->for($tenant)->create();
        $tenantActor = User::factory()->for($tenant)->create();
        $this->grant($tenantActor, $tenant, ['dashboard.view'], 'Expense Administrator');

        $this->assertDenied(
            $this->policy(new TenantContext($tenant, $tenantActor))->delete($tenantActor, $expense),
            'PERMISSION_DENIED',
        );

        $administrator = User::factory()->create(['tenant_id' => null]);
        app(PlatformAdministrator::class)->assign($administrator);
        $administratorRole = Role::query()
            ->where('name', 'Administrator')
            ->whereNull('tenant_id')
            ->firstOrFail();
        $administratorRole->revokePermissionTo('expense.delete');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertDenied(
            $this->policy(new TenantContext($tenant, $administrator))->delete($administrator, $expense),
            'PERMISSION_DENIED',
        );
    }

    private function policy(TenantContext $context): ExpensePolicy
    {
        return new ExpensePolicy(
            $context,
            app(PermissionRegistrar::class),
            app(PlatformAdministrator::class),
        );
    }

    /** @param list<string> $abilities */
    private function grant(User $actor, Tenant $tenant, array $abilities, string $roleName): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $role = Role::query()->firstOrCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => $roleName,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor->assignRole($role);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function revoke(User $actor, Tenant $tenant, string $roleName, string $ability): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        $role = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', $roleName)
            ->firstOrFail();
        $role->revokePermissionTo($ability);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function assertDenied(Response $response, string $message): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame($message, $response->message());
    }

    private function assertNotFound(Response $response): void
    {
        $this->assertTrue($response->denied());
        $this->assertSame('RESOURCE_NOT_FOUND', $response->message());
    }
}
