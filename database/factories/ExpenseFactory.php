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
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->getKey(),
            'planning_year_id' => PlanningYear::factory()->create(['tenant_id' => $tenant->getKey()])->getKey(),
            'cost_center_id' => CostCenter::factory()->create(['tenant_id' => $tenant->getKey()])->getKey(),
            'kind' => ExpenseKind::Ordinary,
            'title' => fake()->sentence(3),
            'notes' => null,
            'project_id' => null,
            'contract_id' => null,
            'lock_version' => 1,
        ];
    }
}
