<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Expenses\Exceptions\PlafondInsufficientException;
use App\Domain\Tenancy\Actions\UpdateTenantSettings;
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
        $tenant = app(UpdateTenantSettings::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'default_vat_rate' => $tenant->default_vat_rate,
                'economic_basis' => 'gross',
                'deletion_reason_required' => $tenant->deletion_reason_required,
            ],
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
            app(UpdateTenantSettings::class)->execute(
                $actor,
                new TenantContext($tenant->fresh(), $actor),
                [
                    'name' => $tenant->name,
                    'timezone' => $tenant->timezone,
                    'default_vat_rate' => $tenant->default_vat_rate,
                    'economic_basis' => 'net',
                    'deletion_reason_required' => $tenant->deletion_reason_required,
                ],
                2,
                (string) str()->uuid(),
            );
            $this->fail('The approved basis must be frozen.');
        } catch (DomainException $exception) {
            $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
        }

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'budget_basis' => 'gross',
            'lock_version' => 2,
        ]);
    }

    public function test_approval_uses_the_locked_persisted_basis_instead_of_a_stale_context_snapshot(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => 'net']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $staleContext = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
            'spend_date' => null,
        ]);

        app(UpdateTenantSettings::class)->execute(
            $actor,
            new TenantContext($tenant->fresh(), $actor),
            [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'default_vat_rate' => $tenant->default_vat_rate,
                'economic_basis' => 'gross',
                'deletion_reason_required' => $tenant->deletion_reason_required,
            ],
            1,
            (string) str()->uuid(),
        );

        $operation = app(ApplyBudgetApproval::class)->execute(
            $actor,
            $staleContext,
            $year,
            new ApplyApprovalData(1, '2027-02-01', null, [
                new ApprovalChangeData((int) $expense->getKey(), 1, '122.00'),
            ]),
            (string) str()->uuid(),
        );

        $this->assertSame('net', $staleContext->budgetBasis->value);
        $this->assertSame('gross', $operation->budget_basis);
        $this->assertSame('gross', $expense->fresh()->approved_basis);
        $this->assertSame('gross', $tenant->fresh()->budget_basis->value);
    }

    public function test_basis_change_revalidates_every_plafond_without_rewriting_components(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => 'net']);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $plafond = Expense::factory()->for($tenant)->plafond()->create([
            'planning_year_id' => $year->getKey(),
        ]);
        $allocation = ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(),
            'entered_amount' => '100.00',
            'net_amount' => '100.00',
            'vat_amount' => '0.00',
            'gross_amount' => '100.00',
        ]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Actual,
            'confirmation_state' => 'to_confirm',
            'spend_date' => '2026-05-01',
            'entered_amount' => '110.00',
            'amount_includes_vat' => true,
            'net_amount' => '90.00',
            'vat_amount' => '20.00',
            'gross_amount' => '110.00',
            'funded_plafond_expense_id' => $plafond->getKey(),
        ]);

        try {
            $this->changeBasis($tenant, $actor, 'gross', 1);
            $this->fail('A Gross-insufficient Plafond allowed the basis change.');
        } catch (PlafondInsufficientException $exception) {
            $this->assertSame($plafond->getKey(), $exception->insufficiency->plafondExpenseId);
            $this->assertSame('10.00', $exception->insufficiency->shortage);
        }

        $this->assertSame('net', $tenant->fresh()->budget_basis->value);
        $this->assertSame(1, $tenant->fresh()->lock_version);
        $this->assertSame('100.00', $allocation->fresh()->gross_amount);
        $this->assertDatabaseMissing('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'event_type' => 'tenant.settings.updated',
        ]);

        ExpenseRow::factory()->for($plafond)->allocationAdjustment($actor)->create([
            'tenant_id' => $tenant->getKey(),
            'position' => 2,
            'entered_amount' => '20.00',
            'net_amount' => '20.00',
            'vat_amount' => '0.00',
            'gross_amount' => '20.00',
        ]);
        $updated = $this->changeBasis($tenant->fresh(), $actor, 'gross', 1);

        $this->assertSame('gross', $updated->budget_basis->value);
        $this->assertSame('100.00', $allocation->fresh()->net_amount);
        $this->assertSame('100.00', $allocation->fresh()->gross_amount);
    }

    private function changeBasis(Tenant $tenant, User $actor, string $basis, int $version): Tenant
    {
        return app(UpdateTenantSettings::class)->execute(
            $actor,
            new TenantContext($tenant, $actor),
            [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'default_vat_rate' => $tenant->default_vat_rate,
                'economic_basis' => $basis,
                'deletion_reason_required' => $tenant->deletion_reason_required,
            ],
            $version,
            (string) str()->uuid(),
        );
    }
}
