<?php

namespace Database\Factories;

use App\Models\BudgetApproval;
use App\Models\BudgetRectification;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetRectification> */
class BudgetRectificationFactory extends Factory
{
    protected $model = BudgetRectification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'budget_approval_id' => BudgetApproval::factory()->completeAggregate(),
            'tenant_id' => fn (array $attributes): int => $this->approval($attributes)->tenant_id,
            'planning_year_id' => fn (array $attributes): int => $this->approval($attributes)->planning_year_id,
            'origin_phase' => 'after_approval',
            'source_identity' => fn (): string => 'expense-row:'.fake()->unique()->numberBetween(1000, 999999999),
            'source_expense_id' => null,
            'source_expense_row_id' => null,
            'actor_user_id' => fn (array $attributes): int => $this->approval($attributes)->approved_by_user_id,
            'note' => 'Rettifica canonica',
            'recorded_at' => now(),
            'revision_batch_id' => fn (array $attributes): int => $this->revisionBatch($attributes)->getKey(),
            'correlation_id' => fn (): string => (string) str()->uuid(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function approval(array $attributes): BudgetApproval
    {
        return BudgetApproval::query()->findOrFail($attributes['budget_approval_id']);
    }

    /** @param array<string, mixed> $attributes */
    private function revisionBatch(array $attributes): RevisionBatch
    {
        $approval = $this->approval($attributes);

        return RevisionBatch::query()->create([
            'tenant_id' => $approval->tenant_id,
            'actor_user_id' => $approval->approved_by_user_id,
            'root_subject_type' => PlanningYear::class,
            'root_subject_id' => $approval->planning_year_id,
            'operation' => 'update',
            'correlation_id' => (string) str()->uuid(),
            'occurred_at' => now(),
        ]);
    }
}
