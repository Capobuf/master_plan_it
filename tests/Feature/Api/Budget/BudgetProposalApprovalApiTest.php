<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Models\AuditEvent;
use App\Models\BudgetApproval;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetProposalApprovalApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_approval_preview_returns_the_complete_strict_read_only_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Licenze',
        ]);
        $estimate = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 1, 'type' => ExpenseType::Estimate,
            'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $quote = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'position' => 2, 'type' => ExpenseType::Quote,
            'net_amount' => '120.00', 'vat_amount' => '26.40', 'gross_amount' => '146.40',
        ]);
        $expense->forceFill(['current_planning_row_id' => $quote->getKey()])->saveQuietly();
        $this->actingAs($user, 'web');
        $before = $this->effects($year, $tenant);

        $response = $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview');

        $response->assertOk()
            ->assertJsonPath('data.planning_year.id', $year->getKey())
            ->assertJsonPath('data.planning_year.state', 'preparation')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.basis', 'net')
            ->assertJsonPath('data.composition.contributor_count', 1)
            ->assertJsonPath('data.total.net', '120.00')
            ->assertJsonPath('data.total.vat', '26.40')
            ->assertJsonPath('data.total.gross', '146.40')
            ->assertJsonPath('data.total.official', '120.00')
            ->assertJsonPath('data.contributors.0.source_identity', 'expense-row:'.$quote->getKey())
            ->assertJsonPath('data.contributors.0.drill_down.authorized', true)
            ->assertJsonPath('data.exclusions.0.source_identity', 'expense-row:'.$estimate->getKey())
            ->assertJsonPath('data.exclusions.0.reason', 'alternative_planning')
            ->assertJsonPath('data.can_approve', true)
            ->assertJsonPath('data.empty_composition', false)
            ->assertJsonMissingPath('data.items')
            ->assertJsonMissingPath('data.approved_amount')
            ->assertJsonMissingPath('data.approved_basis');
        $this->assertSame($before, $this->effects($year->fresh(), $tenant->fresh()));
    }

    public function test_empty_preview_is_successful_but_not_approvable_and_unknown_query_fields_fail(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview')
            ->assertOk()
            ->assertJsonPath('data.composition.contributor_count', 0)
            ->assertJsonPath('data.total.official', '0.00')
            ->assertJsonPath('data.contributors', [])
            ->assertJsonPath('data.empty_composition', true)
            ->assertJsonPath('data.can_approve', false);

        $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview?cost_center_id=1')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_preparation_overview_uses_only_the_target_strict_top_level_dto(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');
        $before = $this->effects($year, $tenant);

        $response = $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey());

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'planning_year', 'currency', 'basis', 'economic_base', 'proposal', 'approved_snapshot',
                'informative_evaluations', 'actuals', 'actions',
            ]])
            ->assertJsonPath('data.planning_year.id', $year->getKey())
            ->assertJsonPath('data.proposal.composition.contributor_count', 0)
            ->assertJsonPath('data.approved_snapshot', null)
            ->assertJsonMissingPath('data.summary')
            ->assertJsonMissingPath('data.expenses')
            ->assertJsonMissingPath('data.mode')
            ->assertJsonMissingPath('data.budget')
            ->assertJsonMissingPath('data.totals')
            ->assertJsonMissingPath('data.plafonds');

        $this->assertSame([
            'planning_year', 'currency', 'basis', 'economic_base', 'proposal', 'approved_snapshot',
            'informative_evaluations', 'actuals', 'actions',
        ], array_keys($response->json('data')));
        $this->assertSame($before, $this->effects($year->fresh(), $tenant->fresh()));
    }

    public function test_budget_only_reader_sees_preview_without_source_link_and_cannot_approve(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        $role = $user->roles()->firstOrFail();
        $role->revokePermissionTo('expense.update');
        $role->revokePermissionTo('expense.view');
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview')
            ->assertOk()
            ->assertJsonPath('data.can_approve', false)
            ->assertJsonPath('data.contributors.0.drill_down.authorized', false)
            ->assertJsonPath('data.contributors.0.drill_down.href', null);
    }

    public function test_foreign_and_missing_preview_years_are_equivalent_non_disclosing_not_found_results(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for($foreign)->create();
        $this->actingAs($user, 'web');

        $foreignResponse = $this->getJson('/api/v1/budget/'.$foreignYear->getKey().'/approval-preview');
        $missingResponse = $this->getJson('/api/v1/budget/999999999/approval-preview');

        $foreignResponse->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $missingResponse->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->assertSame($foreignResponse->json('error.code'), $missingResponse->json('error.code'));
        $this->assertSame($foreignResponse->json('error.fields'), $missingResponse->json('error.fields'));
        foreach (['state', 'total', 'actor', 'count', 'blockers'] as $protectedKey) {
            $foreignResponse->assertJsonMissingPath('error.'.$protectedKey);
            $missingResponse->assertJsonMissingPath('error.'.$protectedKey);
        }
    }

    /** @return array<string, int|string|null> */
    private function effects(PlanningYear $year, Tenant $tenant): array
    {
        return [
            'state' => $year->budget_state instanceof \BackedEnum ? $year->budget_state->value : (string) $year->budget_state,
            'version' => (int) $year->lock_version,
            'history_activated_at' => $year->history_activated_at?->toISOString(),
            'base_lock' => $tenant->economic_basis_locked_at?->toISOString(),
            'approvals' => BudgetApproval::query()->count(),
            'revisions' => RevisionBatch::query()->count(),
            'audits' => AuditEvent::query()->count(),
        ];
    }
}
