<?php

namespace Tests\Feature\Api\Expenses;

use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Contract;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ExpenseApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_register_has_pagination_year_filter_and_exact_money_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'title' => 'Exact expense']);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
            'net_amount' => '120228.00',
            'vat_amount' => '26450.16',
            'gross_amount' => '146678.16',
        ]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses?year='.$year->getKey().'&per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'planning_year_id', 'totals' => ['net', 'vat', 'gross', 'currency', 'official_basis']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
                'totals' => ['net', 'vat', 'gross', 'currency', 'official_basis'],
                'year_options',
            ])
            ->assertJsonPath('data.0.totals.net', '120228.00')
            ->assertJsonPath('data.0.totals.currency', 'EUR')
            ->assertJsonPath('data.0.totals.official_basis', 'net')
            ->assertJsonPath('totals.gross', '146678.16');
    }

    public function test_register_filters_plafond_expenses_for_the_editor_lookup(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'kind' => ExpenseKind::Ordinary,
            'title' => 'Ordinary expense',
        ]);
        $plafond = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'kind' => ExpenseKind::Plafond,
            'title' => 'Eligible Plafond',
        ]);
        ExpenseRow::factory()->for($plafond)->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses?year='.$year->getKey().'&kind=plafond&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plafond->getKey())
            ->assertJsonPath('data.0.kind', 'plafond');

        $this->getJson('/api/v1/expenses?kind=unsupported')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_register_applies_all_operational_filters_and_totals_before_pagination(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year2025 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2025]);
        $year2026 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create(['name' => 'Operations']);
        $otherCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Excluded']);
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Acme Italia']);
        $otherVendor = Vendor::factory()->for($tenant)->create(['name' => 'Secondo Vendor']);
        $project = Project::factory()->for($tenant)->create([
            'cost_center_id' => $center->getKey(),
            'title' => 'Modernizzazione',
        ]);
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'title' => 'Cloud Enterprise',
            'active' => true,
            'lock_version' => 1,
        ]);

        $matching = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2026->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'contract_id' => $contract->getKey(),
            'title' => 'Rinnovo CLOUD annuale',
            'state' => ExpenseState::Closed,
        ]);
        ExpenseRow::factory()->for($matching)->create([
            'position' => 1,
            'vendor_id' => $vendor->getKey(),
            'net_amount' => '10.00',
            'vat_amount' => '2.20',
            'gross_amount' => '12.20',
        ]);
        ExpenseRow::factory()->for($matching)->create([
            'position' => 2,
            'vendor_id' => $otherVendor->getKey(),
            'net_amount' => '30.00',
            'vat_amount' => '6.60',
            'gross_amount' => '36.60',
        ]);

        $excludedByCenter = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2026->getKey(),
            'cost_center_id' => $otherCenter->getKey(),
            'title' => 'Rinnovo cloud escluso',
            'state' => ExpenseState::Closed,
        ]);
        ExpenseRow::factory()->for($excludedByCenter)->create([
            'vendor_id' => $vendor->getKey(),
            'net_amount' => '999.00',
            'vat_amount' => '219.78',
            'gross_amount' => '1218.78',
        ]);

        $excludedByYear = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2025->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'contract_id' => $contract->getKey(),
            'title' => 'Rinnovo cloud anno precedente',
            'state' => ExpenseState::Closed,
        ]);
        ExpenseRow::factory()->for($excludedByYear)->create(['vendor_id' => $vendor->getKey()]);
        $this->actingAs($user, 'web');

        $query = http_build_query([
            'year' => $year2026->getKey(),
            'q' => 'CLOUD',
            'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(),
            'contract_id' => $contract->getKey(),
            'vendor_id' => $vendor->getKey(),
            'state' => 'closed',
            'per_page' => 1,
        ]);

        $this->getJson('/api/v1/expenses?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->getKey())
            ->assertJsonPath('data.0.vendor_count', 2)
            ->assertJsonPath('data.0.vendor_summary', '2 fornitori')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('totals.net', '40.00')
            ->assertJsonPath('totals.vat', '8.80')
            ->assertJsonPath('totals.gross', '48.80');

        $foreignVendor = Vendor::factory()->for(Tenant::factory()->create())->create();
        $this->getJson('/api/v1/expenses?year='.$year2026->getKey().'&vendor_id='.$foreignVendor->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_detail_is_year_scoped_and_exposes_contract_and_row_vendor_labels(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year2025 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2025]);
        $year2026 = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Fornitore Dettaglio']);
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $center->getKey(),
            'title' => 'Contratto Reale',
            'active' => true,
            'lock_version' => 1,
        ]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year2025->getKey(),
            'cost_center_id' => $center->getKey(),
            'contract_id' => $contract->getKey(),
        ]);
        ExpenseRow::factory()->for($expense)->create(['vendor_id' => $vendor->getKey()]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses/'.$expense->getKey().'?year='.$year2026->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->getJson('/api/v1/expenses/'.$expense->getKey().'?year='.$year2025->getKey())
            ->assertOk()
            ->assertJsonPath('data.contract_title', 'Contratto Reale')
            ->assertJsonPath('data.rows.0.vendor_name', 'Fornitore Dettaglio');
    }

    public function test_register_column_preferences_are_persisted_per_user_and_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $columns = [
            ['key' => 'vendor', 'visible' => true],
            ['key' => 'kind', 'visible' => false],
            ['key' => 'contract', 'visible' => true],
            ['key' => 'project', 'visible' => true],
            ['key' => 'cost_center', 'visible' => true],
            ['key' => 'gross', 'visible' => true],
            ['key' => 'net', 'visible' => false],
            ['key' => 'vat', 'visible' => false],
            ['key' => 'state', 'visible' => true],
        ];
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/expenses/register-preferences', [
            'columns' => $columns,
        ])->assertOk()->assertJsonPath('data.columns.0.key', 'vendor');

        $this->getJson('/api/v1/expenses?year='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('column_preferences.0.key', 'vendor')
            ->assertJsonPath('column_preferences.0.visible', true);
    }

    public function test_foreign_expense_is_not_disclosed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $foreign = Expense::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses/'.$foreign->getKey().'?year='.$year->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_create_close_descriptive_update_and_delete_use_api_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $payload = $this->payload($year->getKey(), $center->getKey(), $vendor->getKey(), 'actual');

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'API expense')
            ->assertJsonPath('data.lock_version', 1)
            ->assertJsonPath('data.rows.0.lock_version', 1)
            ->assertJsonPath('data.rows.0.entered_amount', '100.00')
            ->assertJsonPath('data.rows.0.totals.net', '100.00')
            ->assertJsonCount(1, 'data.revision_activity')
            ->assertJsonPath('data.revision_activity.0.operation', 'create');
        $expenseId = (int) $created->json('data.id');
        $rowId = (int) $created->json('data.rows.0.id');
        $this->assertDatabaseHas('expense_rows', [
            'id' => $rowId,
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
        ]);

        $this->withHeaders($this->csrfHeaders())->postJson("/api/v1/expenses/{$expenseId}/close", [
            'lock_version' => 1,
            'outcome' => null,
        ])->assertOk()->assertJsonPath('data.state', 'closed');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/expenses/'.$expenseId, [
            ...$payload,
            'title' => 'Updated expense',
            'lock_version' => 2,
            'rows' => [[...$payload['rows'][0], 'id' => $rowId, 'lock_version' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated expense')
            ->assertJsonPath('data.state', 'closed');

        $this->withHeaders($this->csrfHeaders())->deleteJson('/api/v1/expenses/'.$expenseId, [
            'lock_version' => 3,
        ])->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }

    public function test_client_cannot_supply_calculated_money_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $payload = $this->payload($year->getKey(), $center->getKey(), $vendor->getKey());
        $payload['rows'][0]['net_amount'] = '999999.99';
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseMissing('expenses', ['title' => 'API expense']);
    }

    public function test_expense_row_decimals_reject_a_third_fractional_digit(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        foreach (['quantity', 'unit_price', 'entered_amount', 'vat_rate'] as $field) {
            $payload = $this->payload($year->getKey(), $center->getKey(), $vendor->getKey());
            $payload['rows'][0][$field] = '1.234';

            $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
    }

    public function test_expense_detail_exposes_scale_two_and_cent_accurate_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $payload = $this->payload($year->getKey(), $center->getKey(), $vendor->getKey());
        $payload['rows'][0] = [
            ...$payload['rows'][0],
            'quantity' => '1.25',
            'unit_price' => '10.55',
            'entered_amount' => '0.00',
            'vat_rate' => '10.50',
        ];
        $this->actingAs($user, 'web');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', $payload)
            ->assertCreated()
            ->assertJsonPath('data.rows.0.quantity', '1.25')
            ->assertJsonPath('data.rows.0.unit_price', '10.55')
            ->assertJsonPath('data.rows.0.entered_amount', '13.19')
            ->assertJsonPath('data.rows.0.vat_rate', '10.50')
            ->assertJsonPath('data.rows.0.totals.net', '13.19')
            ->assertJsonPath('data.rows.0.totals.vat', '1.38')
            ->assertJsonPath('data.rows.0.totals.gross', '14.57');
    }

    /** @return array<string, mixed> */
    private function payload(int $year, int $center, int $vendor, string $type = 'estimate'): array
    {
        return [
            'planning_year_id' => $year,
            'cost_center_id' => $center,
            'kind' => 'ordinary',
            'title' => 'API expense',
            'notes' => null,
            'contract_id' => null,
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendor,
                'type' => $type,
                'description' => 'API row',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'vat_rate' => '22.00',
                'is_extra' => false,
                'funded_plafond_expense_id' => null,
                'spend_date' => '2026-01-15',
                'external_reference' => null,
                'is_current_planning' => $type !== 'actual',
            ]],
        ];
    }
}
