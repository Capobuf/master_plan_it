<?php

namespace Tests\Feature\Api\Reporting;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
                    'monthly', 'by_type', 'by_cost_center', 'has_economic_data',
                ],
            ])
            ->assertJsonPath('data.summary.amounts.net', '100.00')
            ->assertJsonPath('data.summary.amounts.vat', '22.00')
            ->assertJsonPath('data.summary.amounts.gross', '122.00')
            ->assertJsonPath('data.summary.currency', 'EUR')
            ->assertJsonMissingPath('data.charts')
            ->assertJsonMissingPath('data.summary.netFormatted');
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
            ->assertJsonPath('data.cost_center_id', $center->getKey())
            ->assertJsonPath('data.summary.amounts.official_current_position', '100.00');

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
                'data' => [['id', 'expense_id', 'net', 'vat', 'gross', 'currency', 'official_basis']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
                'scope' => ['planning_year_id', 'official_basis'],
                'summary' => ['amounts', 'currency'],
                'filters' => ['planning_year_id', 'cost_center_id'],
            ])
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
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
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'net_amount' => $net,
            'vat_amount' => $vat,
            'gross_amount' => $gross,
            'spend_date' => '2026-01-15',
        ]);

        return $expense;
    }
}
