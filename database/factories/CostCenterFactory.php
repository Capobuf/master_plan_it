<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(3, true),
            'active' => true,
            'lock_version' => 1,
        ];
    }
}
