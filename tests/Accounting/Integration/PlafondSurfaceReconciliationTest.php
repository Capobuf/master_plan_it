<?php

namespace Tests\Accounting\Integration;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondSurfaceReconciliationTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_document_register_budget_and_report_expose_identical_plafond_measures_without_legacy_overrun(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => 'net']);
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create(['name' => 'Infrastructure']);
        $this->actingAs($user, 'web');
        $plafondId = (int) $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Infrastructure allocation', 'notes' => null,
            'initial_allocation' => ['description' => 'Initial allocation', 'notes' => null, 'entered_amount' => '3500.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated()->json('data.id');

        $detail = $this->getJson('/api/v1/plafonds/'.$plafondId.'?planning_year_id='.$year->getKey())
            ->assertOk()->json('data.measures');
        $register = $this->getJson('/api/v1/plafonds?planning_year_id='.$year->getKey())
            ->assertOk()->json('data.0.measures');
        $budget = $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey())
            ->assertOk()->assertJsonMissingPath('data.summary.plafond_overrun')->json('data.plafonds.0.measures');
        $report = $this->getJson('/api/v1/plafonds/report?planning_year_id='.$year->getKey())
            ->assertOk()->assertJsonMissingPath('global_plafond_overrun')->json('data.0.measures');

        $this->assertSame($detail, $register);
        $this->assertSame($detail, $budget);
        $this->assertSame($detail, $report);
        $this->assertSame(['allocation', 'coverage_planned', 'consumed', 'available'], array_keys($detail));
    }
}
