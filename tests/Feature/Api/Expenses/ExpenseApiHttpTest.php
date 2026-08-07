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
                'data' => [['id', 'planning_year_id', 'totals' => ['net', 'vat', 'gross', 'currency']]],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
                'totals' => ['net', 'vat', 'gross', 'currency'],
                'year_options',
            ])
            ->assertJsonPath('data.0.totals.net', '120228.00')
            ->assertJsonPath('data.0.totals.currency', 'EUR')
            ->assertJsonPath('totals.gross', '146678.16');
    }

    public function test_foreign_expense_is_not_disclosed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreign = Expense::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/expenses/'.$foreign->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_create_update_confirm_and_delete_use_api_contract(): void
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
            ->assertJsonPath('data.rows.0.entered_amount', '100.000000')
            ->assertJsonPath('data.rows.0.totals.net', '100.00');
        $expenseId = (int) $created->json('data.id');
        $rowId = (int) $created->json('data.rows.0.id');

        $this->withHeaders($this->csrfHeaders())->postJson("/api/v1/expenses/{$expenseId}/rows/{$rowId}/confirm", [
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.confirmation_state', 'confirmed');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/expenses/'.$expenseId, [
            ...$payload,
            'title' => 'Updated expense',
            'lock_version' => 1,
            'rows' => [[...$payload['rows'][0], 'id' => $rowId, 'lock_version' => 2]],
        ])->assertOk()->assertJsonPath('data.title', 'Updated expense');

        $this->withHeaders($this->csrfHeaders())->deleteJson('/api/v1/expenses/'.$expenseId, [
            'lock_version' => 2,
        ])->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
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
