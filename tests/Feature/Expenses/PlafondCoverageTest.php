<?php

namespace Tests\Feature\Expenses;

use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondCoverageTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_ordinary_row_can_be_fully_covered_by_one_same_tenant_year_plafond_across_cost_centers(): void
    {
        [, $user, $year, $plafondCenter, $expenseCenter, $vendor] = $this->workspace();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $plafondCenter);

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->payload($year, $expenseCenter, $vendor, $plafondId))
            ->assertCreated()
            ->assertJsonPath('data.cost_center.id', $expenseCenter->getKey())
            ->assertJsonPath('data.rows.0.funded_plafond.id', $plafondId)
            ->assertJsonPath('data.rows.0.funded_plafond.cost_center.id', $plafondCenter->getKey());
    }

    public function test_coverage_rejects_partial_or_multiple_shapes_and_keeps_foreign_and_missing_relations_equivalent(): void
    {
        [, $user, $year, $center, , $vendor] = $this->workspace();
        $foreignTenant = Tenant::factory()->create();
        $foreignYear = PlanningYear::factory()->for($foreignTenant)->create();
        $foreignCenter = CostCenter::factory()->for($foreignTenant)->create();
        $this->actingAs($this->tenantUser($foreignTenant), 'web');
        $foreignId = $this->createPlafond($foreignYear, $foreignCenter);
        $this->actingAs($user, 'web');

        foreach (['coverage_percentage' => '50.00', 'coverage_amount' => '50.00', 'coverage_allocations' => [1, 2]] as $field => $value) {
            $payload = $this->payload($year, $center, $vendor, null);
            $payload['rows'][0][$field] = $value;
            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['fields' => ['rows.0.'.$field]]]);
        }

        $errors = [];
        foreach ([$foreignId, 999999999] as $id) {
            $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->payload($year, $center, $vendor, $id))
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['fields' => ['rows.0.funded_plafond_expense_id']]]);
            $errors[] = $response->json('error');
        }
        $this->assertSame($errors[0], $errors[1]);
    }

    public function test_extra_budget_and_plafond_coverage_are_xor_in_the_final_row_state(): void
    {
        [, $user, $year, $center, , $vendor] = $this->workspace();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $center);
        $payload = $this->payload($year, $center, $vendor, $plafondId);
        $payload['rows'][0]['is_extra'] = true;

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['rows.0.is_extra']]]);
    }

    /** @return array{Tenant, \App\Models\User, PlanningYear, CostCenter, CostCenter, Vendor} */
    private function workspace(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, $this->tenantUser($tenant), PlanningYear::factory()->for($tenant)->create(), CostCenter::factory()->for($tenant)->create(), CostCenter::factory()->for($tenant)->create(), Vendor::factory()->for($tenant)->create()];
    }

    /** @return array<string, mixed> */
    private function payload(PlanningYear $year, CostCenter $center, Vendor $vendor, ?int $plafondId): array
    {
        return [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => 'ordinary', 'title' => 'Covered expense', 'notes' => null, 'project_id' => null, 'contract_id' => null,
            'rows' => [[
                'position' => 1, 'vendor_id' => $vendor->getKey(), 'type' => 'estimate', 'is_current_planning' => true,
                'description' => 'Whole covered line', 'notes' => null, 'entered_amount' => '100.00', 'amount_includes_vat' => false,
                'vat_rate' => '22.00', 'spend_date' => null, 'external_reference' => null, 'funded_plafond_expense_id' => $plafondId,
            ]],
        ];
    }

    private function createPlafond(PlanningYear $year, CostCenter $center): int
    {
        return (int) $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Funding plafond', 'notes' => null,
            'initial_allocation' => ['description' => 'Initial allocation', 'notes' => null, 'entered_amount' => '3000.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated()->json('data.id');
    }
}
