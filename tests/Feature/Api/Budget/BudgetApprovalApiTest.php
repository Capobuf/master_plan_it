<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Tenancy\Data\TenantContext;
use App\Http\Middleware\AuthorizeApplicationAbility;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Policies\ExpensePolicy;
use App\Support\Authorization\PlatformAdministrator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetApprovalApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_legacy_partial_approval_route_is_absent_without_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/budget/'.$year->getKey().'/approval-decisions', [
                'budget_lock_version' => 1,
                'effective_date' => '2026-02-10',
                'items' => [[
                    'expense_id' => 1,
                    'expense_lock_version' => 1,
                    'approved_amount' => '10.00',
                ]],
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseHas('planning_years', [
            'id' => $year->getKey(),
            'budget_state' => 'preparation',
            'lock_version' => 1,
        ]);
        $this->assertDatabaseCount('budget_approvals', 0);
    }

    public function test_route_catalog_has_no_partial_approval_vocabulary(): void
    {
        $routes = file_get_contents(base_path('routes/api/v1/reporting.php'));

        self::assertIsString($routes);
        $this->assertStringNotContainsString('approval-decisions', $routes);
        $this->assertStringNotContainsString('approved_amount', $routes);
    }

    public function test_budget_mutation_requires_both_existing_abilities_and_same_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $user);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        $request = Request::create('/_foundation/budget-mutation', 'POST');
        $request->attributes->set(TenantContext::class, $context);
        $request->setUserResolver(static fn () => $user);
        $middleware = app(AuthorizeApplicationAbility::class);

        $response = $middleware->handle(
            $request,
            static fn () => response()->noContent(),
            'budget.view',
            'expense.update',
        );
        $this->assertSame(204, $response->getStatusCode());

        $role = $user->roles()->firstOrFail();
        $this->assertInstanceOf(Role::class, $role);
        $role->revokePermissionTo('expense.update');
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->expectException(AuthorizationException::class);
        $middleware->handle(
            $request,
            static fn () => response()->noContent(),
            'budget.view',
            'expense.update',
        );
    }

    public function test_budget_policy_denies_inactive_actor_and_foreign_context_before_data_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignTenant = Tenant::factory()->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());

        $foreignPolicy = new ExpensePolicy(
            new TenantContext($foreignTenant, $user),
            $registrar,
            app(PlatformAdministrator::class),
        );
        $this->assertTrue($foreignPolicy->manageBudget($user)->denied());

        $user->forceFill(['is_active' => false])->save();
        $sameTenantPolicy = new ExpensePolicy(
            new TenantContext($tenant, $user),
            $registrar,
            app(PlatformAdministrator::class),
        );
        $this->assertTrue($sameTenantPolicy->manageBudget($user)->denied());
    }
}
