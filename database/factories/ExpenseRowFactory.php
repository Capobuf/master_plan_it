<?php

namespace Database\Factories;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRow>
 */
class ExpenseRowFactory extends Factory
{
    protected $model = ExpenseRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'tenant_id' => function (array $attributes): ?int {
                $expense = Expense::query()->find($attributes['expense_id'] ?? null);

                return $expense?->tenant_id;
            },
            'position' => 1,
            'vendor_id' => function (array $attributes): ?int {
                $expense = Expense::query()->find($attributes['expense_id'] ?? null);

                return $expense === null
                    ? null
                    : Vendor::factory()->create(['tenant_id' => $expense->tenant_id])->getKey();
            },
            'type' => ExpenseType::Estimate,
            'description' => fake()->sentence(4),
            'notes' => null,
            'quantity' => null,
            'unit_price' => null,
            'entered_amount' => '100.00',
            'amount_includes_vat' => false,
            'vat_rate' => '22.00',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'is_extra' => false,
            'funded_plafond_expense_id' => null,
            'spend_date' => null,
            'period_start' => null,
            'period_end' => null,
            'distribution' => null,
            'external_reference' => null,
            'lock_version' => 1,
        ];
    }

    public function allocationAdjustment(User $creator): static
    {
        return $this->state(fn (): array => [
            'vendor_id' => null,
            'type' => ExpenseType::AllocationAdjustment,
            'created_by_user_id' => $creator->getKey(),
            'confirmation_state' => null,
            'confirmed_by_user_id' => null,
            'confirmed_at' => null,
            'is_system_managed' => false,
            'manual_override_at' => null,
            'contract_term_id' => null,
            'source_key' => null,
            'is_extra' => false,
            'funded_plafond_expense_id' => null,
            'spend_date' => '2026-01-01',
            'period_start' => null,
            'period_end' => null,
            'distribution' => null,
            'external_reference' => null,
        ]);
    }
}
