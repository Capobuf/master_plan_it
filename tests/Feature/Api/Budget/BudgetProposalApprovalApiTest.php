<?php

namespace Tests\Feature\Api\Budget;

use App\Domain\Budget\Data\BudgetApprovalPreview;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\BudgetApproval;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetProposalApprovalApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_approval_preview_returns_the_complete_strict_read_only_contract(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
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
            ->assertJsonPath('data.effective_date_max', '2026-08-13')
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
        $this->assertSame(
            ['planning_year', 'currency', 'basis', 'effective_date_max', 'surface_fingerprint', 'composition', 'total', 'contributors', 'exclusions', 'can_approve', 'empty_composition'],
            array_keys($response->json('data')),
        );
        $this->assertSame(
            ['source_identity', 'kind', 'expense', 'row', 'plafond', 'dimensions', 'amount', 'source_lock_version', 'drill_down'],
            array_keys($response->json('data.contributors.0')),
        );
        $this->assertSame(
            ['source_identity', 'reason', 'expense', 'row', 'amount', 'detail', 'drill_down'],
            array_keys($response->json('data.exclusions.0')),
        );
        $this->assertSame($before, $this->effects($year->fresh(), $tenant->fresh()));
    }

    #[DataProvider('budgetBasisProvider')]
    public function test_net_and_gross_overview_and_preview_keep_the_same_full_composition(string $basis, string $official): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => $basis]);
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $this->actingAs($user, 'web');

        $overview = $this->getJson('/api/v1/budget?planning_year_id='.$year->getKey())->assertOk();
        $preview = $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview')->assertOk();

        $this->assertSame($official, $overview->json('data.proposal.total.official'));
        $this->assertSame($overview->json('data.proposal'), collect($preview->json('data'))->only(['composition', 'total'])->all());
        $this->assertCount(1, $preview->json('data.contributors'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function budgetBasisProvider(): iterable
    {
        yield 'net' => ['net', '100.00'];
        yield 'gross' => ['gross', '122.00'];
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
                'planning_year', 'currency', 'basis', 'surface_fingerprint', 'economic_base', 'proposal', 'approved_snapshot',
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
            'planning_year', 'currency', 'basis', 'surface_fingerprint', 'economic_base', 'proposal', 'approved_snapshot',
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

    public function test_source_navigation_matches_each_real_detail_boundary_and_redacts_deleted_sources(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $ordinary = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Ordinary source',
        ]);
        $row = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
        ]);
        $ordinary->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $deleted = ExpenseRow::factory()->for($ordinary)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Estimate, 'description' => 'Secret tombstone',
        ]);
        $deleted->delete();
        $deletedRoot = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Deleted root',
        ]);
        ExpenseRow::factory()->for($deletedRoot)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Estimate, 'description' => 'Deleted root row',
        ]);
        $deletedRoot->delete();
        $plafond = Expense::factory()->for($tenant)->plafond()->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'title' => 'Plafond source',
        ]);
        ExpenseRow::factory()->for($plafond)->allocationAdjustment($user)->create([
            'tenant_id' => $tenant->getKey(), 'entered_amount' => '50.00', 'net_amount' => '50.00',
            'vat_amount' => '11.00', 'gross_amount' => '61.00',
        ]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        $role = $user->roles()->firstOrFail();
        $role->revokePermissionTo('vendor.view');
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/v1/budget/'.$year->getKey().'/approval-preview')->assertOk();
        $contributors = collect($response->json('data.contributors'))->keyBy('kind');
        $this->assertFalse($contributors['ordinary_current_planning']['drill_down']['authorized']);
        $this->assertNull($contributors['ordinary_current_planning']['drill_down']['href']);
        $this->assertTrue($contributors['plafond_allocation']['drill_down']['authorized']);
        $this->assertSame('/api/v1/plafonds/'.$plafond->getKey(), $contributors['plafond_allocation']['drill_down']['href']);
        $softDeleted = collect($response->json('data.exclusions'))->where('reason', 'soft_deleted');
        $this->assertCount(0, $softDeleted);
        $this->assertStringNotContainsString('Secret tombstone', $response->getContent());
        $this->assertStringNotContainsString('Deleted root row', $response->getContent());
        $this->assertStringNotContainsString('expense-row:'.$deleted->getKey(), $response->getContent());
    }

    public function test_platform_administrator_gets_per_source_navigation(): void
    {
        $tenant = Tenant::factory()->create();
        $administrator = $this->administrator();
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create(['tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute(
            $administrator,
            new TenantContext($tenant, $administrator),
            (int) $year->getKey(),
        );

        $this->assertTrue($preview->proposal->contributors[0]->drillDownAuthorized);
        $this->assertSame('/api/v1/expenses/'.$expense->getKey(), $preview->proposal->contributors[0]->drillDownHref);
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

    public function test_approve_accepts_only_server_evidence_and_returns_the_exact_created_snapshot_summary(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
        [$tenant, $user, $year, $preview] = $this->approvableFixture();
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson(
            '/api/v1/budget/'.$year->getKey().'/approve',
            $this->approvePayload($preview, ['effective_date' => '2024-12-31', 'note' => '  Decisione  ']),
        );

        $response->assertCreated()
            ->assertJsonPath('data.approval.status', 'active')
            ->assertJsonPath('data.approval.planning_year_id', $year->getKey())
            ->assertJsonPath('data.approval.total.official', '120.00')
            ->assertJsonPath('data.approval.effective_date', '2024-12-31')
            ->assertJsonPath('data.approval.note', 'Decisione')
            ->assertJsonPath('data.budget.state', 'approved')
            ->assertJsonPath('data.budget.lock_version', 2)
            ->assertJsonPath('data.economic_base.basis', 'net');
        $this->assertNotNull($response->json('data.economic_base.locked_at'));
        $this->assertSame(['approval', 'budget', 'economic_base'], array_keys($response->json('data')));
        $this->assertSame([
            'id', 'status', 'planning_year_id', 'currency', 'basis', 'total', 'effective_date',
            'recorded_at', 'approved_by', 'note',
        ], array_keys($response->json('data.approval')));
        $this->assertSame(['net', 'vat', 'gross', 'official'], array_keys($response->json('data.approval.total')));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.approval.approved_by')));
        $this->assertSame(['planning_year_id', 'state', 'lock_version'], array_keys($response->json('data.budget')));
        $this->assertSame(['basis', 'locked_at'], array_keys($response->json('data.economic_base')));
        $this->assertDatabaseCount('budget_approvals', 1);
        $this->assertDatabaseCount('budget_approval_items', 1);
        $this->assertSame(1, AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'budget.approved')->count());
        $this->assertSame(1, AuditEvent::query()->where('tenant_id', $tenant->getKey())->where('event_type', 'revision.batch.begin')->count());
    }

    public function test_approve_rejects_client_items_amounts_actor_and_recorded_time_without_effects(): void
    {
        [, $user, $year, $preview] = $this->approvableFixture();
        $this->actingAs($user, 'web');

        foreach (['items', 'approved_amount', 'approved_by_user_id', 'recorded_at', 'idempotency_key'] as $field) {
            $before = $this->effects($year->fresh(), $year->tenant()->firstOrFail());
            $this->withHeaders($this->csrfHeaders())->postJson(
                '/api/v1/budget/'.$year->getKey().'/approve',
                $this->approvePayload($preview, [$field => $field === 'items' ? [] : 'forbidden']),
            )->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
            $this->assertSame($before, $this->effects($year->fresh(), $year->tenant()->firstOrFail()));
        }

        foreach ([
            ['composition' => [...$this->approvePayload($preview)['composition'], 'extra' => true]],
            ['composition' => [
                ...$this->approvePayload($preview)['composition'],
                'versions' => [...$this->approvePayload($preview)['composition']['versions'], 'extra' => true],
            ]],
        ] as $nestedOverride) {
            $before = $this->effects($year->fresh(), $year->tenant()->firstOrFail());
            $this->withHeaders($this->csrfHeaders())->postJson(
                '/api/v1/budget/'.$year->getKey().'/approve',
                $this->approvePayload($preview, $nestedOverride),
            )->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
            $this->assertSame($before, $this->effects($year->fresh(), $year->tenant()->firstOrFail()));
        }
    }

    public function test_future_date_stale_version_and_changed_fingerprint_have_exact_errors_and_zero_effects(): void
    {
        CarbonImmutable::setTestNow('2026-08-13 10:30:00 UTC');
        [, $user, $year, $preview] = $this->approvableFixture('Pacific/Kiritimati');
        $this->actingAs($user, 'web');
        $before = $this->effects($year, $year->tenant()->firstOrFail());

        $this->withHeaders($this->csrfHeaders())->postJson(
            '/api/v1/budget/'.$year->getKey().'/approve',
            $this->approvePayload($preview, ['effective_date' => '2026-08-15']),
        )->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame($before, $this->effects($year->fresh(), $year->tenant()->firstOrFail()));

        $stale = $this->approvePayload($preview);
        $stale['composition']['versions']['budget_lock_version'] = 999;
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/approve', $stale)
            ->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');
        $this->assertSame($before, $this->effects($year->fresh(), $year->tenant()->firstOrFail()));

        $changed = $this->approvePayload($preview);
        $changed['composition']['fingerprint'] = 'sha256:'.str_repeat('0', 64);
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/budget/'.$year->getKey().'/approve', $changed)
            ->assertConflict()->assertJsonPath('error.code', 'BUDGET_COMPOSITION_STALE');

        $this->assertSame($before, $this->effects($year->fresh(), $year->tenant()->firstOrFail()));
    }

    public function test_reused_correlation_is_diagnostic_and_second_request_revalidates_state_instead_of_replaying(): void
    {
        [, $user, $year, $preview] = $this->approvableFixture();
        $this->actingAs($user, 'web');
        $correlationId = (string) str()->uuid();
        $headers = $this->csrfHeaders() + ['X-Correlation-ID' => $correlationId];
        $payload = $this->approvePayload($preview);

        $this->withHeaders($headers)->postJson('/api/v1/budget/'.$year->getKey().'/approve', $payload)->assertCreated();
        $this->withHeaders($headers)->postJson('/api/v1/budget/'.$year->getKey().'/approve', $payload)
            ->assertConflict()->assertJsonPath('error.code', 'BUDGET_STATE_CONFLICT');

        $this->assertSame(1, BudgetApproval::query()->where('correlation_id', $correlationId)->count());
    }

    public function test_foreign_year_resolution_precedes_invalid_body_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $foreignYear = PlanningYear::factory()->for($foreign)->create();
        $this->actingAs($user, 'web');

        $foreignResponse = $this->withHeaders($this->csrfHeaders())->postJson(
            '/api/v1/budget/'.$foreignYear->getKey().'/approve',
            ['items' => [['approved_amount' => '999999.00']]],
        )->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $missingResponse = $this->withHeaders($this->csrfHeaders())->postJson(
            '/api/v1/budget/999999999/approve',
            ['items' => [['approved_amount' => '999999.00']]],
        )->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->assertSame($foreignResponse->json('error.code'), $missingResponse->json('error.code'));
        $this->assertSame($foreignResponse->json('error.fields'), $missingResponse->json('error.fields'));
        $this->assertSame(0, BudgetApproval::query()->count());
    }

    public function test_missing_expense_update_ability_precedes_invalid_body_and_has_no_effects(): void
    {
        [$tenant, $user, $year] = $this->approvableFixture();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        $role = $user->roles()->where('roles.tenant_id', $tenant->getKey())->firstOrFail();
        $role->syncPermissions(['budget.view']);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $this->actingAs($user, 'web');
        $before = $this->effects($year, $tenant);

        $this->withHeaders($this->csrfHeaders())->postJson(
            '/api/v1/budget/'.$year->getKey().'/approve',
            ['items' => [['approved_amount' => '999999.00']]],
        )->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');

        $this->assertSame($before, $this->effects($year->fresh(), $tenant->fresh()));
    }

    public function test_approve_rejects_every_query_parameter_and_never_treats_query_as_command_body(): void
    {
        [$tenant, $user, $year, $preview] = $this->approvableFixture();
        $this->actingAs($user, 'web');
        $before = $this->effects($year, $tenant);

        foreach ([
            ['/api/v1/budget/'.$year->getKey().'/approve?effective_date=2026-08-13', []],
            ['/api/v1/budget/'.$year->getKey().'/approve?effective_date=2026-08-13', $this->approvePayload($preview)],
            ['/api/v1/budget/'.$year->getKey().'/approve?composition[versions][budget_lock_version]=1', $this->approvePayload($preview)],
        ] as [$uri, $body]) {
            $this->withHeaders($this->csrfHeaders())->postJson($uri, $body)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_FAILED');
            $this->assertSame($before, $this->effects($year->fresh(), $tenant->fresh()));
        }
    }

    /** @return array{Tenant, User, PlanningYear, BudgetApprovalPreview} */
    private function approvableFixture(string $timezone = 'Europe/Rome'): array
    {
        $tenant = Tenant::factory()->create(['timezone' => $timezone]);
        $user = $this->tenantUser($tenant);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'entered_amount' => '120.00', 'net_amount' => '120.00', 'vat_amount' => '26.40', 'gross_amount' => '146.40',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($user, new TenantContext($tenant, $user), (int) $year->getKey());

        return [$tenant, $user, $year, $preview];
    }

    /** @return array<string, mixed> */
    private function approvePayload(BudgetApprovalPreview $preview, array $overrides = []): array
    {
        return array_replace([
            'effective_date' => '2026-08-13',
            'note' => null,
            'composition' => [
                'schema_version' => $preview->proposal->composition->schemaVersion,
                'fingerprint' => $preview->proposal->composition->fingerprint,
                'versions' => [
                    'budget_lock_version' => $preview->proposal->composition->budgetLockVersion,
                    'projection_version' => $preview->proposal->composition->projectionVersion,
                ],
            ],
        ], $overrides);
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
            'items' => DB::table('budget_approval_items')->count(),
            'revisions' => RevisionBatch::query()->count(),
            'revision_items' => RevisionBatchItem::query()->count(),
            'year_versions' => Version::query()
                ->where('versionable_type', $year->getMorphClass())
                ->where('versionable_id', $year->getKey())
                ->count(),
            'audits' => AuditEvent::query()->count(),
        ];
    }
}
