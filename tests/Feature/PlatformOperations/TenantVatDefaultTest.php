<?php

namespace Tests\Feature\PlatformOperations;

use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class TenantVatDefaultTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_vat_default_applies_only_to_new_expense_rows_and_contract_terms(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['default_vat_rate' => '22.00', 'lock_version' => 1]);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $costCenter = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->actingAs($administrator, 'web');
        $headers = $this->csrfHeaders();
        $this->withHeaders($headers)->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();

        $oldExpense = $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->expensePayload(
            (int) $year->getKey(),
            (int) $costCenter->getKey(),
            (int) $vendor->getKey(),
            'Expense before VAT change',
        ))->assertCreated();
        $oldContract = $this->withHeaders($headers)->postJson('/api/v1/contracts', $this->contractPayload(
            (int) $vendor->getKey(),
            (int) $costCenter->getKey(),
            'Contract before VAT change',
        ))->assertSuccessful();

        $oldExpenseRowId = (int) $oldExpense->json('data.rows.0.id');
        $oldContractTermId = (int) $oldContract->json('data.terms.0.id');
        $this->assertDatabaseHas('expense_rows', [
            'id' => $oldExpenseRowId, 'vat_rate' => '22.00', 'net_amount' => '100.00',
            'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $this->assertDatabaseHas('contract_terms', [
            'id' => $oldContractTermId, 'vat_rate' => '22.00', 'net_amount' => '100.00',
            'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);

        $this->withHeaders($headers)->putJson('/api/v1/tenant-settings', [
            'name' => $tenant->name,
            'timezone' => $tenant->timezone,
            'default_vat_rate' => '20.00',
            'budget_basis' => 'net',
            'deletion_reason_required' => false,
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.default_vat_rate', '20.00');

        $this->assertSame('22.00', (string) ExpenseRow::query()->findOrFail($oldExpenseRowId)->vat_rate);
        $this->assertSame('22.00', (string) ContractTerm::query()->findOrFail($oldContractTermId)->vat_rate);

        $newExpense = $this->withHeaders($headers)->postJson('/api/v1/expenses', $this->expensePayload(
            (int) $year->getKey(),
            (int) $costCenter->getKey(),
            (int) $vendor->getKey(),
            'Expense after VAT change',
        ))->assertCreated();
        $newContract = $this->withHeaders($headers)->postJson('/api/v1/contracts', $this->contractPayload(
            (int) $vendor->getKey(),
            (int) $costCenter->getKey(),
            'Contract after VAT change',
        ))->assertSuccessful();

        $this->assertDatabaseHas('expense_rows', [
            'id' => (int) $newExpense->json('data.rows.0.id'), 'vat_rate' => '20.00', 'net_amount' => '100.00',
            'vat_amount' => '20.00', 'gross_amount' => '120.00',
        ]);
        $this->assertDatabaseHas('contract_terms', [
            'id' => (int) $newContract->json('data.terms.0.id'), 'vat_rate' => '20.00', 'net_amount' => '100.00',
            'vat_amount' => '20.00', 'gross_amount' => '120.00',
        ]);
        $this->assertDatabaseHas('expense_rows', ['id' => $oldExpenseRowId, 'vat_rate' => '22.00', 'gross_amount' => '122.00']);
        $this->assertDatabaseHas('contract_terms', ['id' => $oldContractTermId, 'vat_rate' => '22.00', 'gross_amount' => '122.00']);
    }

    /** @return array<string, mixed> */
    private function expensePayload(int $yearId, int $costCenterId, int $vendorId, string $title): array
    {
        return [
            'planning_year_id' => $yearId,
            'cost_center_id' => $costCenterId,
            'kind' => 'ordinary',
            'title' => $title,
            'notes' => null,
            'contract_id' => null,
            'rows' => [[
                'position' => 1,
                'vendor_id' => $vendorId,
                'type' => 'estimate',
                'description' => $title,
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'is_extra' => false,
                'funded_plafond_expense_id' => null,
                'spend_date' => '2026-01-15',
                'external_reference' => null,
                'is_current_planning' => true,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function contractPayload(int $vendorId, int $costCenterId, string $title): array
    {
        return [
            'vendor_id' => $vendorId,
            'cost_center_id' => $costCenterId,
            'title' => $title,
            'description' => null,
            'active' => true,
            'renewal_date' => null,
            'renewal_notice_days' => null,
            'renewal_notes' => null,
            'terms' => [[
                'local_key' => str()->uuid()->toString(),
                'effective_start' => '2026-01-01',
                'effective_end' => '2026-12-31',
                'billing_cycle' => 'monthly',
                'quantity' => null,
                'unit_price' => null,
                'entered_amount' => '100.00',
                'amount_includes_vat' => false,
                'auto_renew' => false,
            ]],
        ];
    }
}
