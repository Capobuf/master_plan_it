<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Actions\UpdateTenant;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class TenantBudgetBasisFreezeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_budget_basis_can_change_before_approval_and_is_frozen_afterward(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => 'net']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $tenant = app(UpdateTenant::class)->execute(
            $actor,
            $tenant,
            ['budget_basis' => 'gross'],
            1,
            (string) str()->uuid(),
        );
        $this->assertSame('gross', $tenant->budget_basis->value);

        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
            'spend_date' => null,
        ]);
        $context = new TenantContext($tenant, $actor);
        app(ApplyBudgetApproval::class)->execute(
            $actor,
            $context,
            $year,
            new ApplyApprovalData(1, '2026-02-01', null, [
                new ApprovalChangeData((int) $expense->getKey(), 1, '100.00'),
            ]),
            (string) str()->uuid(),
        );

        try {
            app(UpdateTenant::class)->execute(
                $actor,
                $tenant->fresh(),
                ['budget_basis' => 'net'],
                2,
                (string) str()->uuid(),
            );
            $this->fail('The approved basis must be frozen.');
        } catch (DomainException $exception) {
            $this->assertSame('TENANT_BUDGET_BASIS_LOCKED', $exception->getMessage());
        }

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'budget_basis' => 'gross',
            'lock_version' => 2,
        ]);
    }
}
