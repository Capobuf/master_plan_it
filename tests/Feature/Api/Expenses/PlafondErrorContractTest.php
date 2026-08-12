<?php

namespace Tests\Feature\Api\Expenses;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondErrorContractTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_insufficient_covered_actual_returns_the_422_redacted_impact_envelope_and_persists_nothing(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $plafondId = (int) $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Capacity plafond', 'notes' => null,
            'initial_allocation' => ['description' => 'Initial allocation', 'notes' => null, 'entered_amount' => '3000.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated()->json('data.id');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->actualPayload($year, $center, $vendor, $plafondId, '500.00'))
            ->assertCreated();

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->actualPayload($year, $center, $vendor, $plafondId, '2700.00'))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PLAFOND_INSUFFICIENT')
            ->assertJsonPath('error.details.plafond_expense_id', $plafondId)
            ->assertJsonPath('error.details.currency', 'EUR')
            ->assertJsonPath('error.details.basis', 'net')
            ->assertJsonPath('error.details.allocated', '3000.00')
            ->assertJsonPath('error.details.available', '2500.00')
            ->assertJsonPath('error.details.required', '2700.00')
            ->assertJsonPath('error.details.shortage', '200.00')
            ->assertJsonStructure(['error' => ['correlation_id', 'fields' => ['rows.0.funded_plafond_expense_id'], 'details' => ['impact' => ['current', 'proposed', 'blocking_rows']]]])
            ->assertJsonMissingPath('error.details.plafond_title')
            ->assertJsonMissingPath('error.details.plafond_cost_center');
        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseCount('expense_rows', 2);
    }

    /** @return array<string, mixed> */
    private function actualPayload(PlanningYear $year, CostCenter $center, Vendor $vendor, int $plafondId, string $amount): array
    {
        return ['planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => 'ordinary', 'title' => 'Capacity actual '.$amount, 'notes' => null, 'project_id' => null, 'contract_id' => null,
            'rows' => [['position' => 1, 'vendor_id' => $vendor->getKey(), 'type' => 'actual', 'description' => 'Actual', 'notes' => null, 'entered_amount' => $amount, 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'spend_date' => '2026-08-12', 'external_reference' => null, 'funded_plafond_expense_id' => $plafondId]]];
    }
}
