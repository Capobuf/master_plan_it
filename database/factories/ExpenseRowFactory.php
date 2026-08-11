<?php

namespace Database\Factories;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseRow;
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
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'entered_amount' => '100.00',
            'amount_includes_vat' => false,
            'vat_rate' => '22.00',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'is_extra' => false,
            'funded_plafond_expense_id' => null,
            'spend_date' => '2026-01-15',
            'period_start' => null,
            'period_end' => null,
            'distribution' => null,
            'external_reference' => null,
            'lock_version' => 1,
        ];
    }
}
