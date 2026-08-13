<?php

namespace Database\Factories;

use App\Domain\Budget\Enums\BudgetApprovalStatus;
use App\Models\BudgetApproval;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BudgetApproval> */
class BudgetApprovalFactory extends Factory
{
    protected $model = BudgetApproval::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'planning_year_id' => fn (array $attributes): int => $this->planningYearId((int) $attributes['tenant_id']),
            'status' => BudgetApprovalStatus::Active,
            'effective_date' => '2026-08-13',
            'recorded_at' => now(),
            'approved_by_user_id' => fn (array $attributes): int => User::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'is_active' => true,
            ])->getKey(),
            'approved_by_name' => fn (array $attributes): string => User::query()->findOrFail($attributes['approved_by_user_id'])->name,
            'approval_note' => null,
            'currency_code' => 'EUR',
            'budget_basis' => 'net',
            'total_net_amount' => '100.00',
            'total_vat_amount' => '22.00',
            'total_gross_amount' => '122.00',
            'total_official_amount' => '100.00',
            'contributor_count' => 1,
            'composition_schema_version' => 'budget-proposal-composition/v1',
            'projection_version' => 'annual-economic-projection/v1',
            'composition_fingerprint' => 'sha256:'.str_repeat('a', 64),
            'approval_revision_batch_id' => fn (array $attributes): int => $this->revisionBatchId(
                (int) $attributes['tenant_id'],
                (int) $attributes['planning_year_id'],
                (int) $attributes['approved_by_user_id'],
            ),
            'correlation_id' => fn (): string => (string) str()->uuid(),
            'annulled_at' => null,
            'annulled_by_user_id' => null,
            'annulled_by_name' => null,
            'annulment_note' => null,
            'annulment_revision_batch_id' => null,
            'annulment_correlation_id' => null,
        ];
    }

    public function annulled(): static
    {
        return $this->state(fn (): array => [
            'status' => BudgetApprovalStatus::Annulled,
        ])->afterMaking(function (BudgetApproval $approval): void {
            $approval->forceFill([
                'annulled_at' => now(),
                'annulled_by_user_id' => $approval->approved_by_user_id,
                'annulled_by_name' => $approval->approved_by_name,
                'annulment_note' => 'Correzione della proposta',
                'annulment_revision_batch_id' => $this->revisionBatchId(
                    (int) $approval->tenant_id,
                    (int) $approval->planning_year_id,
                    (int) $approval->approved_by_user_id,
                ),
                'annulment_correlation_id' => (string) str()->uuid(),
            ]);
        });
    }

    private function planningYearId(int $tenantId): int
    {
        return PlanningYear::query()->where('tenant_id', $tenantId)->first()?->getKey()
            ?? PlanningYear::factory()->create(['tenant_id' => $tenantId])->getKey();
    }

    private function revisionBatchId(int $tenantId, int $planningYearId, int $actorId): int
    {
        return RevisionBatch::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actorId,
            'root_subject_type' => PlanningYear::class,
            'root_subject_id' => $planningYearId,
            'operation' => 'update',
            'correlation_id' => (string) str()->uuid(),
            'occurred_at' => now(),
        ])->getKey();
    }
}
