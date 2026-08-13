<?php

namespace Tests\Accounting\Integration;

use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BudgetProposalAuthorizationBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_both_queries_fail_closed_before_year_lookup_for_unprotected_tenantless_mismatch_and_inactive_actors(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create();
        $tenantless = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        $member = $this->actor($tenant, ['budget.view']);
        $inactive = $this->actor($tenant, ['budget.view']);
        $inactive->forceFill(['is_active' => false])->save();

        foreach ([AnnualBudgetQuery::class, BudgetApprovalPreviewQuery::class] as $queryClass) {
            $this->assertDenied(
                fn () => app($queryClass)->execute($tenantless, new TenantContext($tenant, $tenantless), (int) $year->getKey()),
                'PERMISSION_DENIED',
            );
            $this->assertDenied(
                fn () => app($queryClass)->execute($member, new TenantContext($foreign, $member), (int) $year->getKey()),
                'PERMISSION_DENIED',
            );
            $this->assertDenied(
                fn () => app($queryClass)->execute($inactive, new TenantContext($tenant, $inactive), (int) $year->getKey()),
                'PERMISSION_DENIED',
            );
        }

        $tenant->forceFill(['state' => TenantState::Inactive])->save();
        $inactiveTenantContext = new TenantContext($tenant->refresh(), $member->refresh());
        foreach ([AnnualBudgetQuery::class, BudgetApprovalPreviewQuery::class] as $queryClass) {
            $this->assertDenied(
                fn () => app($queryClass)->execute($member->refresh(), $inactiveTenantContext, (int) $year->getKey()),
                'TENANT_INACTIVE',
            );
        }
    }

    public function test_protected_administrator_can_read_an_inactive_selected_tenant(): void
    {
        $tenant = Tenant::factory()->create(['state' => TenantState::Inactive]);
        $year = PlanningYear::factory()->for($tenant)->create();
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($administrator);
        $context = new TenantContext($tenant, $administrator);

        $this->assertSame($year->getKey(), app(AnnualBudgetQuery::class)->execute($administrator, $context, (int) $year->getKey())['planning_year']['id']);
        $this->assertSame($year->getKey(), app(BudgetApprovalPreviewQuery::class)->execute($administrator, $context, (int) $year->getKey())->planningYearId);
    }

    /** @param list<string> $abilities */
    private function actor(Tenant $tenant, array $abilities): User
    {
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Budget boundary '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        try {
            $actor->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        return $actor;
    }

    /** @param callable(): mixed $operation */
    private function assertDenied(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Authorization unexpectedly succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
