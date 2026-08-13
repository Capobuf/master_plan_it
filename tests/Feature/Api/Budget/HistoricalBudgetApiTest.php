<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\BudgetApproval;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class HistoricalBudgetApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_cutoff_before_activation_returns_the_stable_contract_error(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey().'&as_of=2026-03-01T09:59:59Z')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HISTORY_BEFORE_ACTIVATION');
    }

    public function test_historical_budget_is_tenant_scoped_and_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/budget?planning_year_id='.$foreignYear->getKey().'&as_of=2026-03-01')
            ->assertNotFound();
    }

    public function test_as_of_returns_the_strict_target_from_existing_history_without_legacy_schema_reads_or_side_effects(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($user, new TenantContext($tenant, $user), $year, (string) str()->uuid());
        $row->forceFill(['net_amount' => '999.00', 'vat_amount' => '219.78', 'gross_amount' => '1218.78'])->saveQuietly();
        $this->actingAs($user, 'web');
        $before = [
            RevisionBatch::query()->count(), AuditEvent::query()->count(), BudgetApproval::query()->count(),
            (int) $year->fresh()->lock_version,
        ];

        $response = $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey().'&as_of=2026-03-01T10:30:00Z');

        $response->assertOk()
            ->assertJsonPath('data.proposal.total.net', '100.00')
            ->assertJsonPath('data.actions.can_view_approval_preview', false)
            ->assertJsonPath('data.actions.can_approve', false)
            ->assertJsonPath('data.actions.can_annul_active_approval', false)
            ->assertJsonMissingPath('data.mode')
            ->assertJsonMissingPath('data.expenses')
            ->assertJsonMissingPath('data.summary')
            ->assertJsonMissingPath('data.historical_context');
        $this->assertSame([
            'planning_year', 'currency', 'basis', 'surface_fingerprint', 'economic_base', 'proposal', 'approved_snapshot',
            'informative_evaluations', 'actuals', 'actions',
        ], array_keys($response->json('data')));
        $this->assertSame($before, [
            RevisionBatch::query()->count(), AuditEvent::query()->count(), BudgetApproval::query()->count(),
            (int) $year->fresh()->lock_version,
        ]);
    }
}
