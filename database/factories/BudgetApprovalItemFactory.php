<?php

namespace Database\Factories;

use App\Domain\Budget\Enums\ApprovalContributorKind;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetApprovalItem> */
class BudgetApprovalItemFactory extends Factory
{
    protected $model = BudgetApprovalItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $rowId = fake()->unique()->numberBetween(1000, 999999999);

        return [
            'budget_approval_id' => BudgetApproval::factory(),
            'tenant_id' => fn (array $attributes): int => $this->approval($attributes)->tenant_id,
            'planning_year_id' => fn (array $attributes): int => $this->approval($attributes)->planning_year_id,
            'budget_basis' => fn (array $attributes): string => $this->approval($attributes)->budget_basis,
            'source_identity' => 'expense-row:'.$rowId,
            'source_lock_version' => 1,
            'component_kind' => ApprovalContributorKind::OrdinaryCurrentPlanning,
            'expense_id' => fake()->numberBetween(1000, 999999999),
            'expense_row_id' => $rowId,
            'expense_kind' => ExpenseKind::Ordinary,
            'expense_title' => 'Spesa fotografata',
            'row_type' => ExpenseType::Quote,
            'row_description' => 'Preventivo corrente',
            'cost_center_id' => fake()->numberBetween(1000, 999999999),
            'cost_center_name' => 'Centro storico',
            'vendor_id' => null,
            'vendor_name' => null,
            'project_id' => null,
            'project_title' => null,
            'contract_id' => null,
            'contract_title' => null,
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'official_amount' => fn (array $attributes): string => $attributes['budget_basis'] === 'net' ? '100.00' : '122.00',
        ];
    }

    public function plafondAllocation(): static
    {
        $expenseId = fake()->unique()->numberBetween(1000, 999999999);

        return $this->state(fn (): array => [
            'source_identity' => 'plafond-allocation:'.$expenseId,
            'component_kind' => ApprovalContributorKind::PlafondAllocation,
            'expense_id' => $expenseId,
            'expense_row_id' => null,
            'expense_kind' => ExpenseKind::Plafond,
            'row_type' => null,
            'row_description' => null,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function approval(array $attributes): BudgetApproval
    {
        return BudgetApproval::query()->findOrFail($attributes['budget_approval_id']);
    }
}
