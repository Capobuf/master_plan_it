<?php

namespace Tests\Feature\Api\Expenses;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ExpenseApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_register_and_detail_expose_the_same_canonical_projection(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2025]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
        ]);
        $planning = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Quote,
            'net_amount' => '110.00',
            'vat_amount' => '24.20',
            'gross_amount' => '134.20',
        ]);
        $expense->forceFill(['current_planning_row_id' => $planning->getKey()])->saveQuietly();
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'position' => 2,
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-02-10',
            'net_amount' => '-5.00',
            'vat_amount' => '-1.10',
            'gross_amount' => '-6.10',
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.economic_year_label', 2025)
            ->assertJsonPath('data.0.totals.current_planning.official', '110.00')
            ->assertJsonPath('data.0.totals.actual.official', '-5.00')
            ->assertJsonPath('totals.current_planning.official', '110.00')
            ->assertJsonPath('basis', 'net');

        $this->getJson('/api/v1/expenses/'.$expense->getKey().'?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.economic_year_label', 2025)
            ->assertJsonPath('data.rows.1.spend_date', '2026-02-10')
            ->assertJsonPath('data.totals.actual.official', '-5.00')
            ->assertJsonMissingPath('data.state')
            ->assertJsonMissingPath('data.closure_outcome');
    }

    public function test_preview_is_non_persistent_and_create_accepts_calculated_planning_plus_out_of_year_actual(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2025, 'active' => true]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $payload = $this->payload((int) $year->getKey(), (int) $center->getKey(), (int) $vendor->getKey());
        $this->actingAs($user, 'web');

        $before = Expense::query()->count();
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.current_planning_row_position', 1)
            ->assertJsonPath('data.totals.current_planning.net', '110.00')
            ->assertJsonPath('data.totals.actual.net', '-5.00');
        $this->assertSame($before, Expense::query()->count());

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.economic_year_label', 2025)
            ->assertJsonPath('data.rows.0.entered_amount', '110.00')
            ->assertJsonPath('data.rows.1.spend_date', '2026-02-10')
            ->assertJsonPath('data.totals.actual.official', '-5.00');
        $this->assertDatabaseHas('expenses', ['id' => $created->json('data.id')]);
    }

    public function test_foreign_path_and_body_relationships_do_not_disclose_tenant_data(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $foreignTenant = Tenant::factory()->create();
        $foreign = Expense::factory()->for($foreignTenant)->create();
        $foreignCenter = CostCenter::factory()->for($foreignTenant)->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses/'.$foreign->getKey().'?planning_year_id='.$year->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $payload = $this->payload((int) $year->getKey(), (int) $foreignCenter->getKey(), (int) $vendor->getKey());
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['fields' => ['cost_center_id']]]);
    }

    /** @return array<string, mixed> */
    private function payload(int $year, int $center, int $vendor): array
    {
        return [
            'planning_year_id' => $year,
            'cost_center_id' => $center,
            'kind' => 'ordinary',
            'title' => 'API expense',
            'notes' => null,
            'project_id' => null,
            'contract_id' => null,
            'rows' => [
                [
                    'position' => 1,
                    'vendor_id' => $vendor,
                    'type' => 'quote',
                    'is_current_planning' => true,
                    'description' => 'Planning row',
                    'notes' => null,
                    'quantity' => '2.00',
                    'unit_price' => '55.00',
                    'amount_includes_vat' => false,
                    'vat_rate' => '22.00',
                    'spend_date' => null,
                    'external_reference' => null,
                ],
                [
                    'position' => 2,
                    'vendor_id' => $vendor,
                    'type' => 'actual',
                    'is_current_planning' => false,
                    'description' => 'Actual row',
                    'notes' => 'Evento reale',
                    'entered_amount' => '-5.00',
                    'amount_includes_vat' => false,
                    'vat_rate' => '22.00',
                    'spend_date' => '2026-02-10',
                    'external_reference' => null,
                ],
            ],
        ];
    }
}
