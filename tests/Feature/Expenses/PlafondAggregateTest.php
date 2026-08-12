<?php

namespace Tests\Feature\Expenses;

use App\Models\CostCenter;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondAggregateTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_adjustments_are_additive_and_a_zero_adjustment_is_rejected_without_mutation(): void
    {
        [, $user, $year, $center] = $this->workspace();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $center);

        foreach (['1000.00', '-500.00'] as $index => $amount) {
            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments', [
                ...$this->adjustmentRequest($amount, $index + 1),
            ])->assertCreated();
        }

        $this->getJson('/api/v1/plafonds/'.$plafondId.'?planning_year_id='.$year->getKey())
            ->assertOk()->assertJsonPath('data.measures.allocation.official', '3500.00');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments', [
            ...$this->adjustmentRequest('0.00', 3),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_reduction_preview_and_confirmation_use_the_final_consumed_state_and_preserve_covered_rows(): void
    {
        [$tenant, $user, $year, $center, $vendor] = $this->workspace();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $center, '3500.00');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->coveredActualPayload($year, $center, $vendor, $plafondId, '2500.00'))
            ->assertCreated();
        $request = $this->adjustmentRequest('-1200.00', 1);

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments/preview', $request)
            ->assertOk()->assertJsonPath('data.proposed.allocation.official', '2300.00')
            ->assertJsonPath('data.proposed.consumed.official', '2500.00')
            ->assertJsonPath('data.proposed.available.official', '-200.00')
            ->assertJsonPath('data.shortage', '200.00')
            ->assertJsonPath('data.can_confirm', false)
            ->assertJsonStructure(['data' => ['blocking_rows' => [['expense_id', 'row_id', 'expense_cost_center', 'amount']]]]);
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments', $request)
            ->assertUnprocessable()->assertJsonPath('error.code', 'PLAFOND_INSUFFICIENT');
        $this->assertSame(2, ExpenseRow::query()
            ->where('tenant_id', $tenant->getKey())
            ->count());
    }

    /** @return array{Tenant, User, PlanningYear, CostCenter, Vendor} */
    private function workspace(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, $this->tenantUser($tenant), PlanningYear::factory()->for($tenant)->create(), CostCenter::factory()->for($tenant)->create(), Vendor::factory()->for($tenant)->create()];
    }

    /** @return array<string, mixed> */
    private function adjustment(string $amount): array
    {
        return ['description' => 'Adjustment', 'notes' => null, 'entered_amount' => $amount, 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'];
    }

    /** @return array<string, mixed> */
    private function adjustmentRequest(string $amount, int $lockVersion): array
    {
        return ['lock_version' => $lockVersion, 'adjustment' => $this->adjustment($amount)];
    }

    private function createPlafond(PlanningYear $year, CostCenter $center, string $initialAllocation = '3000.00'): int
    {
        return (int) $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Infrastructure allocation', 'notes' => null,
            'initial_allocation' => $this->adjustment($initialAllocation),
        ])->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function coveredActualPayload(PlanningYear $year, CostCenter $center, Vendor $vendor, int $plafondId, string $amount): array
    {
        return ['planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => 'ordinary', 'title' => 'Covered actual', 'notes' => null, 'project_id' => null, 'contract_id' => null,
            'rows' => [['position' => 1, 'vendor_id' => $vendor->getKey(), 'type' => 'actual', 'description' => 'Actual', 'notes' => null, 'entered_amount' => $amount, 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'spend_date' => '2026-08-12', 'external_reference' => null, 'funded_plafond_expense_id' => $plafondId]]];
    }
}
