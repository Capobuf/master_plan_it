<?php

namespace Database\Factories;

use App\Domain\Expenses\Enums\ExpenseKind;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'planning_year_id' => function (array $attributes): int {
                $tenantId = $attributes['tenant_id'];
                $year = PlanningYear::query()
                    ->where('tenant_id', $tenantId)
                    ->where('year_label', 2026)
                    ->first();

                return ($year ?? PlanningYear::factory()->create([
                    'tenant_id' => $tenantId,
                    'year_label' => 2026,
                ]))->getKey();
            },
            'cost_center_id' => function (array $attributes): int {
                return CostCenter::factory()->create(['tenant_id' => $attributes['tenant_id']])->getKey();
            },
            'kind' => ExpenseKind::Ordinary,
            'title' => fake()->sentence(3),
            'notes' => null,
            'project_id' => null,
            'contract_id' => null,
            'current_planning_row_id' => null,
            'moved_from_expense_id' => null,
            'credit_for_expense_id' => null,
            'lock_version' => 1,
        ];
    }

    public function plafond(): static
    {
        return $this->state(fn (): array => [
            'kind' => ExpenseKind::Plafond,
            'project_id' => null,
            'contract_id' => null,
            'current_planning_row_id' => null,
        ]);
    }
}
