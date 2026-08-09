<?php

namespace Tests\Feature\Api\Expenses;

use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ExpenseProjectApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_project_relation_round_trips_through_expense_detail_and_register(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->create(['title' => 'Progetto API']);
        $this->actingAs($user, 'web');

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $this->payload(
            (int) $year->getKey(),
            (int) $center->getKey(),
            (int) $vendor->getKey(),
            (int) $project->getKey(),
        ))->assertCreated()
            ->assertJsonPath('data.project_id', $project->getKey())
            ->assertJsonPath('data.project_title', 'Progetto API');

        $expenseId = (int) $created->json('data.id');
        $this->getJson('/api/v1/expenses/'.$expenseId)
            ->assertOk()->assertJsonPath('data.project_id', $project->getKey());
        $this->getJson('/api/v1/expenses?year='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.project_id', $project->getKey())
            ->assertJsonPath('data.0.project_title', 'Progetto API');
    }

    public function test_expense_api_rejects_project_and_contract_together_without_partial_write(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $project = Project::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Contratto API',
            'active' => true,
            'lock_version' => 1,
        ]);
        $payload = $this->payload((int) $year->getKey(), (int) $center->getKey(), (int) $vendor->getKey(), (int) $project->getKey());
        $payload['contract_id'] = $contract->getKey();
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseMissing('expenses', ['tenant_id' => $tenant->getKey(), 'title' => 'Spesa progetto API']);
    }

    /** @return array<string, mixed> */
    private function payload(int $yearId, int $centerId, int $vendorId, int $projectId): array
    {
        return [
            'planning_year_id' => $yearId,
            'cost_center_id' => $centerId,
            'kind' => 'ordinary',
            'title' => 'Spesa progetto API',
            'notes' => null,
            'project_id' => $projectId,
            'contract_id' => null,
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendorId,
                'type' => 'estimate',
                'description' => 'Riga progetto',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.000000',
                'amount_includes_vat' => false,
                'vat_rate' => '22.000000',
                'is_extra' => false,
                'funded_plafond_expense_id' => null,
                'spend_date' => '2026-01-15',
                'period_start' => null,
                'period_end' => null,
                'distribution' => null,
                'external_reference' => null,
            ]],
        ];
    }
}
