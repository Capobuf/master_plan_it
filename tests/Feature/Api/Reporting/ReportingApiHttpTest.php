<?php

namespace Tests\Feature\Api\Reporting;

use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ReportingApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_dashboard_returns_server_calculated_exact_dataset_without_chart_props(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->expenseWithRow($tenant, $year, '100.00', '22.00', '122.00');
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/dashboard?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'scope' => ['planning_year_id', 'year', 'currency', 'official_basis'],
                    'summary' => ['official_basis', 'currency', 'amounts'],
                    'monthly', 'by_type', 'by_cost_center', 'by_project', 'has_economic_data',
                    'year_options', 'selected_year_id',
                    'ancillary' => ['recentExpenses', 'expenseCounts', 'generatedContractPlanning', 'activeContracts', 'upcomingContractEvents'],
                ],
            ])
            ->assertJsonPath('data.summary.amounts.net', '100.00')
            ->assertJsonPath('data.summary.amounts.vat', '22.00')
            ->assertJsonPath('data.summary.amounts.gross', '122.00')
            ->assertJsonPath('data.summary.currency', 'EUR')
            ->assertJsonMissingPath('data.charts')
            ->assertJsonMissingPath('data.summary.netFormatted');
    }

    public function test_dashboard_defaults_year_and_returns_safe_ancillary_domain_data(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => (int) now()->year, 'active' => true]);
        $expense = $this->expenseWithRow($tenant, $year, '100.00', '22.00', '122.00');
        $vendor = Vendor::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->create(['title' => 'Migrazione Microsoft 365']);
        $expense->forceFill(['title' => 'Rinnovo Microsoft 365', 'project_id' => $project->getKey()])->save();
        $expense->currentPlanningRow()->update(['vendor_id' => $vendor->getKey()]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => 'actual',
            'net_amount' => '25.00',
            'vat_amount' => '5.50',
            'gross_amount' => '30.50',
            'spend_date' => '2026-02-15',
        ]);
        $center = CostCenter::factory()->for($tenant)->create();
        Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Active contract',
            'active' => true,
            'renewal_date' => now()->addMonth()->toDateString(),
            'lock_version' => 1,
        ]);
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/v1/dashboard')->assertOk();
        $response->assertJsonPath('data.selected_year_id', $year->getKey())
            ->assertJsonPath('data.ancillary.recentExpenses.0.id', $expense->getKey())
            ->assertJsonPath('data.ancillary.recentExpenses.0.cost_center', $expense->costCenter->name)
            ->assertJsonPath('data.ancillary.recentExpenses.0.project', 'Migrazione Microsoft 365')
            ->assertJsonPath('data.ancillary.recentExpenses.0.vendor', $vendor->name)
            ->assertJsonPath('data.ancillary.recentExpenses.0.planned', '100.00')
            ->assertJsonPath('data.ancillary.recentExpenses.0.actual', '25.00')
            ->assertJsonPath('data.ancillary.recentExpenses.0.state', 'open')
            ->assertJsonPath('data.ancillary.expenseCounts.total', 1)
            ->assertJsonPath('data.ancillary.expenseCounts.open', 1)
            ->assertJsonPath('data.ancillary.expenseCounts.closed', 0)
            ->assertJsonPath('data.by_project.Migrazione Microsoft 365', '125.00')
            ->assertJsonMissingPath('data.ancillary.recentExpenses.0.href')
            ->assertJsonPath('data.ancillary.activeContracts.0.label', 'Active contract')
            ->assertJsonMissingPath('data.ancillary.activeContracts.0.href')
            ->assertJsonPath('data.ancillary.upcomingContractEvents.0.event_type', 'renewal');
    }

    public function test_dashboard_without_planning_years_returns_empty_dataset(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.scope', null)
            ->assertJsonPath('data.selected_year_id', null)
            ->assertJsonPath('data.has_economic_data', false)
            ->assertJsonPath('data.by_project', [])
            ->assertJsonPath('data.ancillary.expenseCounts.total', 0)
            ->assertJsonCount(0, 'data.year_options');
    }

    public function test_dashboard_ability_denial_is_uniform(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            $user->roles()->firstOrFail()->revokePermissionTo('dashboard.view');
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_budget_applies_same_tenant_cost_center_filter(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $foreignCenter = CostCenter::factory()->for($foreignTenant)->create();
        $this->expenseWithRow($tenant, $year, '100.00', '22.00', '122.00', $center);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey().'&cost_center='.$center->getKey())
            ->assertOk()
            ->assertJsonPath('data.budget.planning_year_id', $year->getKey())
            ->assertJsonPath('data.expenses.0.cost_center_id', $center->getKey())
            ->assertJsonPath('data.summary.proposed', '100.00');

        $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey().'&cost_center='.$foreignCenter->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_report_uses_standard_pagination_and_filter_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->expenseWithRow($tenant, $year, '100.00', '22.00', '122.00');
        $this->expenseWithRow($tenant, $year, '200.00', '44.00', '244.00');
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/reports?year='.$year->getKey().'&page=2&per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'key', 'label', 'group_by', 'proposed', 'approved', 'actual',
                    'residual', 'variance', 'utilization_percentage', 'open_expenses',
                    'closed_expenses', 'unapproved_actual_expenses', 'plafond_expenses',
                ]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'mode', 'requested_as_of', 'cutoff_utc', 'read_only',
                'budget' => ['planning_year_id', 'year', 'state', 'lock_version'],
                'summary' => ['proposed', 'approved_current', 'actual', 'residual', 'variance'],
                'filters' => ['planning_year_id', 'cost_center_id', 'group_by'],
            ])
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('filters.group_by', 'cost_center');
    }

    public function test_reporting_requires_authentication_and_tenant_year_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);

        $this->getJson('/api/v1/dashboard?planning_year_id='.$year->getKey())
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');

        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/reports?planning_year_id='.$foreignYear->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    private function expenseWithRow(
        Tenant $tenant,
        PlanningYear $year,
        string $net,
        string $vat,
        string $gross,
        ?CostCenter $costCenter = null,
    ): Expense {
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => ($costCenter ?? CostCenter::factory()->for($tenant)->create())->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'net_amount' => $net,
            'vat_amount' => $vat,
            'gross_amount' => $gross,
            'spend_date' => '2026-01-15',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->save();

        return $expense;
    }
}
