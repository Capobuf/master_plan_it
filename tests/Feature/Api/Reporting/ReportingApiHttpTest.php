<?php

namespace Tests\Feature\Api\Reporting;

use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\Version;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ReportingApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_dashboard_is_an_exact_canonical_projection_consumer(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = $this->expenseWithProjection($tenant, $year);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/dashboard?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonPath('data.planning_year_id', $year->getKey())
            ->assertJsonPath('data.economic_year_label', 2026)
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.basis', 'net')
            ->assertJsonPath('data.totals.current_planning.official', '100.00')
            ->assertJsonPath('data.totals.actual.official', '25.00')
            ->assertJsonPath('data.expense_count', 1)
            ->assertJsonPath('data.recent_expenses.0.expense_id', $expense->getKey())
            ->assertJsonMissingPath('data.scope')
            ->assertJsonMissingPath('data.summary')
            ->assertJsonMissingPath('data.expense_counts')
            ->assertJsonMissingPath('data.recent_expenses.0.state');
    }

    public function test_annual_read_endpoints_require_the_canonical_year_parameter_and_reject_aliases(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');

        foreach (['/api/v1/dashboard', '/api/v1/budget', '/api/v1/reports'] as $path) {
            $this->getJson($path)->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
            $this->getJson($path.'?year='.$year->getKey())->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
    }

    public function test_budget_is_unfiltered_and_rejects_every_presentation_filter(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $foreignCenter = CostCenter::factory()->for(Tenant::factory()->create())->create();
        $this->expenseWithProjection($tenant, $year, $center);
        $this->actingAs($user, 'web');

        $base = '/api/v1/budget?planning_year_id='.$year->getKey();
        $this->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.planning_year.id', $year->getKey())
            ->assertJsonPath('data.proposal.total.official', '100.00')
            ->assertJsonPath('data.actuals.official', '25.00');
        $this->getJson($base.'&cost_center_id='.$center->getKey())
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson($base.'&cost_center='.$center->getKey())
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->getJson($base.'&cost_center_id='.$foreignCenter->getKey())
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_report_paginates_canonical_groups_and_filters_project_and_vendor(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $projectA = Project::factory()->for($tenant)->create(['title' => 'Project A']);
        $projectB = Project::factory()->for($tenant)->create(['title' => 'Project B']);
        $vendorA = Vendor::factory()->for($tenant)->create(['name' => 'Vendor A']);
        $vendorB = Vendor::factory()->for($tenant)->create(['name' => 'Vendor B']);
        $this->expenseWithProjection($tenant, $year, null, $projectA, $vendorA, '100.00', '25.00');
        $this->expenseWithProjection($tenant, $year, null, $projectB, $vendorB, '200.00', '10.00');
        $this->actingAs($user, 'web');

        $base = '/api/v1/reports?planning_year_id='.$year->getKey().'&group_by=expense';
        $this->getJson($base.'&page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('totals.current_planning.official', '300.00')
            ->assertJsonPath('totals.actual.official', '35.00')
            ->assertJsonMissingPath('visualization')
            ->assertJsonMissingPath('filters.state');
        $this->getJson($base.'&project_id='.$projectA->getKey())
            ->assertOk()
            ->assertJsonPath('totals.current_planning.official', '100.00')
            ->assertJsonPath('totals.actual.official', '25.00');
        $this->getJson($base.'&vendor_id='.$vendorB->getKey())
            ->assertOk()
            ->assertJsonPath('totals.current_planning.official', '200.00')
            ->assertJsonPath('filters.vendor_id', $vendorB->getKey());
        $this->getJson($base.'&state=closed')
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_report_current_and_as_of_use_independent_internal_projection_models(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $project = Project::factory()->for($tenant)->create(['title' => 'Historical project']);
        $expense = $this->expenseWithProjection($tenant, $year, project: $project, planned: '100.00', actual: '0.00');
        $historicalExpenseTitle = (string) $expense->title;
        $originalYearVersion = (int) $year->lock_version + 1;
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($user, new TenantContext($tenant, $user), $year, (string) str()->uuid());
        $expense->rows()->where('type', ExpenseType::Quote)->firstOrFail()
            ->forceFill(['net_amount' => '200.00', 'gross_amount' => '200.00'])->saveQuietly();
        $expense->forceFill(['title' => 'Current expense'])->saveQuietly();
        $project->forceFill(['title' => 'Current project'])->saveQuietly();
        DB::table('planning_years')->where('id', $year->getKey())->update([
            'budget_state' => 'closed',
            'lock_version' => 42,
            'history_activated_at' => '2026-03-01 11:00:00',
        ]);
        $this->actingAs($user, 'web');

        $base = '/api/v1/reports?planning_year_id='.$year->getKey();
        $cutoff = '&as_of=2026-03-01T10:30:00Z';
        $current = $this->getJson($base.'&group_by=project')->assertOk()
            ->assertJsonPath('mode', 'current')
            ->assertJsonPath('budget.state', 'closed')
            ->assertJsonPath('budget.lock_version', 42)
            ->assertJsonPath('budget.warning', 'BUDGET_CLOSED')
            ->assertJsonPath('budget.history_activated_at', '2026-03-01T11:00:00.000000Z')
            ->assertJsonPath('data.0.label', 'Current project');
        $this->assertSame(
            ['planning_year_id', 'year', 'state', 'lock_version', 'warning', 'history_activated_at'],
            array_keys($current->json('budget')),
        );
        $this->getJson($base)->assertOk()
            ->assertJsonPath('mode', 'current')
            ->assertJsonPath('summary.proposed', '200.00');
        $historical = $this->getJson($base.'&group_by=project'.$cutoff)->assertOk()
            ->assertJsonPath('mode', 'historical')
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('budget.state', 'preparation')
            ->assertJsonPath('budget.lock_version', $originalYearVersion)
            ->assertJsonPath('budget.warning', null)
            ->assertJsonPath('budget.history_activated_at', '2026-03-01T10:00:00.000000Z')
            ->assertJsonPath('data.0.label', 'Historical project')
            ->assertJsonPath('summary.proposed', '100.00');
        $this->assertSame(
            ['planning_year_id', 'year', 'state', 'lock_version', 'warning', 'history_activated_at'],
            array_keys($historical->json('budget')),
        );
        $this->getJson($base.'&group_by=expense'.$cutoff)->assertOk()
            ->assertJsonPath('data.0.label', $historicalExpenseTitle);
    }

    public function test_first_report_activates_history_after_normal_expense_revisions_and_retry_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 09:00:00', 'UTC'));
        $this->actingAs($user, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/expenses', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
            'kind' => 'ordinary', 'title' => 'Before activation', 'notes' => null,
            'project_id' => null, 'contract_id' => null,
            'rows' => [[
                'position' => 1, 'vendor_id' => $vendor->getKey(), 'type' => 'quote',
                'is_current_planning' => true, 'description' => 'Quoted', 'notes' => null,
                'entered_amount' => '100.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00',
                'spend_date' => null, 'external_reference' => null,
            ]],
        ])->assertCreated();
        $this->assertNull($year->fresh()->history_activated_at);
        $this->assertGreaterThan(0, RevisionBatch::query()->where('root_subject_type', (new Expense)->getMorphClass())->count());

        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        $path = '/api/v1/reports?planning_year_id='.$year->getKey();
        $this->getJson($path)->assertOk()
            ->assertJsonPath('budget.history_activated_at', '2026-03-01T10:00:00.000000Z');
        $effects = [RevisionBatch::query()->count(), Version::query()->count(), (int) $year->fresh()->lock_version];

        $this->getJson($path)->assertOk();
        $this->assertSame($effects, [RevisionBatch::query()->count(), Version::query()->count(), (int) $year->fresh()->lock_version]);
        $this->getJson($path.'&as_of=2026-03-01T10:30:00Z')->assertOk()
            ->assertJsonPath('mode', 'historical')
            ->assertJsonPath('budget.history_activated_at', '2026-03-01T10:00:00.000000Z');
    }

    public function test_reporting_auth_ability_and_tenant_boundaries_fail_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->getJson('/api/v1/dashboard?planning_year_id='.$year->getKey())
            ->assertUnauthorized()->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED');

        $user = $this->tenantUser($tenant);
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            $user->roles()->firstOrFail()->revokePermissionTo('dashboard.view');
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
        $this->actingAs($user, 'web');
        $this->getJson('/api/v1/dashboard?planning_year_id='.$year->getKey())
            ->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $foreignYear = PlanningYear::factory()->for(Tenant::factory()->create())->create(['year_label' => 2026]);
        $this->getJson('/api/v1/reports?planning_year_id='.$foreignYear->getKey())
            ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_budget_and_report_expose_canonical_plafond_measures_without_overrun_aliases(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $this->actingAs($user, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/plafonds', [
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Reporting plafond', 'notes' => null,
            'initial_allocation' => ['description' => 'Initial allocation', 'notes' => null, 'entered_amount' => '3000.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'date' => '2026-08-12'],
        ])->assertCreated();

        $budget = $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey())
            ->assertOk()
            ->assertJsonMissingPath('data.summary')
            ->assertJsonMissingPath('data.plafonds')
            ->json('data.proposal.total');
        $report = $this->getJson('/api/v1/reports?planning_year_id='.$year->getKey().'&group_by=expense')
            ->assertOk()
            ->assertJsonStructure(['plafonds' => [['measures' => ['allocation', 'coverage_planned', 'consumed', 'available']]]])
            ->assertJsonMissingPath('global_plafond_overrun')
            ->assertJsonMissingPath('summary.plafond_overrun')
            ->json('plafonds.0.measures');

        $this->assertSame($budget, $report['allocation']);
    }

    private function expenseWithProjection(
        Tenant $tenant,
        PlanningYear $year,
        ?CostCenter $center = null,
        ?Project $project = null,
        ?Vendor $vendor = null,
        string $planned = '100.00',
        string $actual = '25.00',
    ): Expense {
        $center ??= CostCenter::factory()->for($tenant)->create();
        $vendor ??= Vendor::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $center->getKey(),
            'project_id' => $project?->getKey(),
        ]);
        $planning = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'position' => 1,
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Quote,
            'entered_amount' => $planned,
            'net_amount' => $planned,
            'vat_amount' => '0.00',
            'gross_amount' => $planned,
            'spend_date' => null,
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'position' => 2,
            'vendor_id' => $vendor->getKey(),
            'type' => ExpenseType::Actual,
            'entered_amount' => $actual,
            'net_amount' => $actual,
            'vat_amount' => '0.00',
            'gross_amount' => $actual,
            'spend_date' => '2027-02-15',
        ]);
        $expense->forceFill(['current_planning_row_id' => $planning->getKey()])->saveQuietly();

        return $expense;
    }
}
