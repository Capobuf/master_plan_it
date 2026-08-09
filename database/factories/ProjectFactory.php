<?php

namespace Database\Factories;

use App\Domain\Projects\Enums\ProjectStage;
use App\Models\CostCenter;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cost_center_id' => fn (array $attributes): int => CostCenter::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'title' => fake()->sentence(3),
            'stage' => ProjectStage::Idea,
            'deferred_target_planning_year_id' => null,
            'lock_version' => 1,
            'deleted_by_user_id' => null,
            'deleted_by_at' => null,
            'deletion_reason' => null,
        ];
    }
}
