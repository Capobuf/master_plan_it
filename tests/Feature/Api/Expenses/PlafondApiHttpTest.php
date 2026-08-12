<?php

namespace Tests\Feature\Api\Expenses;

use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlafondApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_dedicated_plafond_routes_create_and_return_the_canonical_four_measures(): void
    {
        [$tenant, $user, $year, $center] = $this->workspace();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', $this->creationPayload($year, $center));

        $response->assertCreated()
            ->assertJsonPath('data.planning_year_id', $year->getKey())
            ->assertJsonPath('data.cost_center.id', $center->getKey())
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.basis', 'net')
            ->assertJsonPath('data.measures.allocation.official', '3000.00')
            ->assertJsonPath('data.measures.coverage_planned.official', '0.00')
            ->assertJsonPath('data.measures.consumed.official', '0.00')
            ->assertJsonPath('data.measures.available.official', '3000.00')
            ->assertJsonMissingPath('data.measures.overrun')
            ->assertJsonMissingPath('data.measures.residual');

        $this->assertDatabaseHas('expenses', [
            'tenant_id' => $tenant->getKey(),
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'kind' => 'plafond',
        ]);
    }

    public function test_list_detail_preview_and_adjustment_have_no_generic_expense_alias(): void
    {
        [, $user, $year, $center] = $this->workspace();
        $this->actingAs($user, 'web');
        $plafondId = $this->createPlafond($year, $center);

        $this->getJson('/api/v1/plafonds?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.id', $plafondId)
            ->assertJsonStructure(['data' => [['measures' => ['allocation', 'coverage_planned', 'consumed', 'available']]]]);
        $this->getJson('/api/v1/plafonds/'.$plafondId.'?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', $plafondId);
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments/preview', $this->adjustmentRequest('-500.00', 1))
            ->assertOk()
            ->assertJsonPath('data.requested', '-500.00')
            ->assertJsonPath('data.can_confirm', true);
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds/'.$plafondId.'/allocation-adjustments', [
            ...$this->adjustmentRequest('-500.00', 1),
        ])->assertCreated()->assertJsonPath('data.measures.allocation.official', '2500.00');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => 'plafond', 'title' => 'Not a dedicated plafond', 'notes' => null,
            'project_id' => null, 'contract_id' => null, 'rows' => [],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['kind']]]);
    }

    public function test_foreign_and_missing_plafond_ids_are_non_leaking_for_path_and_body_references(): void
    {
        [, $user, $year] = $this->workspace();
        $foreignTenant = Tenant::factory()->create();
        $foreignYear = PlanningYear::factory()->for($foreignTenant)->create();
        $foreignCenter = CostCenter::factory()->for($foreignTenant)->create();
        $this->actingAs($this->tenantUser($foreignTenant), 'web');
        $foreignId = $this->createPlafond($foreignYear, $foreignCenter);
        $this->actingAs($user, 'web');

        $pathErrors = [];
        foreach ([$foreignId, 999999999] as $id) {
            $response = $this->getJson('/api/v1/plafonds/'.$id.'?planning_year_id='.$year->getKey())
                ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
            $pathErrors[] = collect($response->json('error'))->except('correlation_id')->all();
        }
        $this->assertSame($pathErrors[0], $pathErrors[1]);
    }

    public function test_plafond_reads_and_writes_fail_closed_for_inactive_actor_and_tenant(): void
    {
        [$tenant, $user, $year, $center] = $this->workspace();
        $this->actingAs($user, 'web');

        $user->update(['is_active' => false]);
        $this->getJson('/api/v1/plafonds?planning_year_id='.$year->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', $this->creationPayload($year, $center))
            ->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');

        $user->update(['is_active' => true]);
        $tenant->update(['state' => 'inactive']);
        $this->getJson('/api/v1/plafonds?planning_year_id='.$year->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'TENANT_INACTIVE');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', $this->creationPayload($year, $center))
            ->assertForbidden()->assertJsonPath('error.code', 'TENANT_INACTIVE');
    }

    public function test_plafond_read_and_write_reuse_exact_expense_abilities(): void
    {
        [, $user, $year, $center] = $this->workspace();
        $this->actingAs($user, 'web');
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($user->tenant_id);
        try {
            $user->roles()->firstOrFail()->revokePermissionTo('expense.view');
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }

        $this->getJson('/api/v1/plafonds?planning_year_id='.$year->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', $this->creationPayload($year, $center))
            ->assertCreated();
    }

    /** @return array{Tenant, \App\Models\User, PlanningYear, CostCenter} */
    private function workspace(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, $this->tenantUser($tenant), PlanningYear::factory()->for($tenant)->create(), CostCenter::factory()->for($tenant)->create()];
    }

    /** @return array<string, mixed> */
    private function creationPayload(PlanningYear $year, CostCenter $center): array
    {
        return [
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Infrastructure allocation',
            'notes' => null,
            'initial_allocation' => $this->adjustmentPayload('3000.00'),
        ];
    }

    /** @return array<string, mixed> */
    private function adjustmentPayload(string $amount = '-500.00'): array
    {
        return [
            'description' => 'Allocation adjustment',
            'notes' => null,
            'entered_amount' => $amount,
            'amount_includes_vat' => false,
            'vat_rate' => '22.00',
            'date' => '2026-08-12',
        ];
    }

    /** @return array<string, mixed> */
    private function adjustmentRequest(string $amount, int $lockVersion): array
    {
        return ['lock_version' => $lockVersion, 'adjustment' => $this->adjustmentPayload($amount)];
    }

    private function createPlafond(PlanningYear $year, CostCenter $center): int
    {
        return (int) $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/plafonds', $this->creationPayload($year, $center))
            ->assertCreated()
            ->json('data.id');
    }
}
