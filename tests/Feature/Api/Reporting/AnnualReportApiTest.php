<?php

namespace Tests\Feature\Api\Reporting;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class AnnualReportApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_http_contract_exposes_every_annual_grouping_with_the_shared_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'approved_amount' => '80.00',
            'approved_basis' => 'net',
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'type' => 'quote',
            'spend_date' => null,
            'entered_amount' => '100.000000',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $this->actingAs($user, 'web');

        foreach (['cost_center', 'project', 'contract', 'vendor', 'expense'] as $groupBy) {
            $this->getJson('/api/v1/reports?planning_year_id='.$year->getKey().'&group_by='.$groupBy)
                ->assertOk()
                ->assertJsonPath('filters.group_by', $groupBy)
                ->assertJsonPath('summary.proposed', '100.00')
                ->assertJsonPath('summary.approved_current', '80.00')
                ->assertJsonPath('data.0.group_by', $groupBy)
                ->assertJsonPath('data.0.proposed', '100.00')
                ->assertJsonPath('data.0.approved', '80.00');
        }
    }
}
