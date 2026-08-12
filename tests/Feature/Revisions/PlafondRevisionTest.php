<?php

namespace Tests\Feature\Revisions;

use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondRevisionTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_restore_reintroducing_a_covered_actual_revalidates_final_capacity_without_success_evidence(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $center);
        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->actualPayload($year, $center, $vendor, $plafondId))
            ->assertCreated()->json('data');
        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/expenses/'.$created['id'], [
            ...$this->actualPayload($year, $center, $vendor, null),
            'lock_version' => $created['lock_version'],
            'rows' => [[
                ...$this->actualPayload($year, $center, $vendor, null)['rows'][0],
                'id' => $created['rows'][0]['id'],
                'lock_version' => $created['rows'][0]['lock_version'],
            ]],
        ])->assertOk();
        $history = $this->getJson('/api/v1/expenses/'.$created['id'].'/history?year='.$year->getKey())
            ->assertOk()->json('data');
        $source = collect($history)->firstWhere('operation', 'create')['id'];
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments', [
            'lock_version' => 1,
            'adjustment' => ['description' => 'Sustainable after removing coverage', 'notes' => null, 'entered_amount' => '-700.00', 'amount_includes_vat' => false,
                'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated();
        $auditCount = AuditEvent::query()->count();

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/'.$created['id'].'/history/'.$source.'/restore', ['lock_version' => 2])
            ->assertUnprocessable()->assertJsonPath('error.code', 'PLAFOND_INSUFFICIENT')
            ->assertJsonPath('error.details.shortage', '200.00')
            ->assertJsonStructure(['error' => ['details' => ['impact' => ['current', 'proposed', 'blocking_rows']]]]);
        $this->assertDatabaseHas('expenses', ['id' => $created['id'], 'lock_version' => 2]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    private function createPlafond(PlanningYear $year, CostCenter $center): int
    {
        return (int) $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Revision funding', 'notes' => null,
            'initial_allocation' => ['description' => 'Initial allocation', 'notes' => null, 'entered_amount' => '3000.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function actualPayload(PlanningYear $year, CostCenter $center, Vendor $vendor, ?int $fundedPlafondId): array
    {
        return ['planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => 'ordinary', 'title' => 'Revision actual', 'notes' => null, 'project_id' => null, 'contract_id' => null,
            'rows' => [['position' => 1, 'vendor_id' => $vendor->getKey(), 'type' => 'actual', 'description' => 'Covered actual', 'notes' => null, 'entered_amount' => '2500.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'spend_date' => '2026-08-12', 'external_reference' => null, 'funded_plafond_expense_id' => $fundedPlafondId]]];
    }
}
