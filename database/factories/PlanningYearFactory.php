<?php

namespace Database\Factories;

use App\Domain\Budget\Enums\BudgetState;
use App\Models\PlanningYear;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningYear>
 */
class PlanningYearFactory extends Factory
{
    protected $model = PlanningYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'year_label' => fake()->unique()->numberBetween(2000, 2099),
            'active' => true,
            'budget_state' => BudgetState::Preparation,
            'history_activated_at' => null,
            'lock_version' => 1,
        ];
    }
}
