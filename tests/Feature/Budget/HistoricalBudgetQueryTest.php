<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Revisions\Actions\ApplyOperationalRevisionRetention;
use App\Domain\Revisions\Queries\OperationalRevisionQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class HistoricalBudgetQueryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_delete_is_a_tombstone_and_never_exposes_a_partial_state(): void
    {
        [$actor, $context, $year, $expense] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());
        $this->assertCount(0, app(OperationalRevisionQuery::class)->visibleForRoot($context, $expense));

        $this->travelTo(CarbonImmutable::parse('2026-03-01 11:00:00', 'UTC'));
        app(DeleteExpense::class)->execute($actor, $context, $expense, 1, false, (string) str()->uuid());

        $before = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01T10:30:00Z',
        );
        $after = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01T11:30:00Z',
        );

        $this->assertSame([$expense->getKey()], collect($before['expenses'])->pluck('id')->all());
        $this->assertSame([], $after['expenses']);
        $this->assertSame('100.00', $before['summary']['proposed']);
        $this->assertSame('0.00', $after['summary']['proposed']);
    }

    public function test_date_only_cutoff_uses_tenant_local_end_of_day_and_normalizes_to_utc(): void
    {
        [$actor, $context, $year] = $this->fixture('Europe/Rome');
        $this->travelTo(CarbonImmutable::parse('2026-02-28 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());

        $result = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01',
        );

        $this->assertSame('2026-03-01T22:59:59.999999Z', $result['cutoff_utc']);
        $this->assertSame('historical', $result['mode']);
        $this->assertTrue($result['read_only']);
    }

    public function test_operational_retention_and_physical_pruning_preserve_annual_history_snapshots(): void
    {
        [$actor, $context, $year, $expense] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());
        $expected = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01T10:30:00Z',
        );

        foreach (range(1, 10) as $position) {
            $this->travelTo(CarbonImmutable::parse('2026-03-01 11:00:00', 'UTC')->addMinutes($position));
            $current = $expense->fresh();
            $row = $current->rows()->firstOrFail();
            $amount = number_format(100 + $position, 2, '.', '');
            app(UpdateExpense::class)->execute(
                $actor,
                $context,
                $current,
                new SaveExpenseData(
                    (int) $year->getKey(),
                    (int) $current->cost_center_id,
                    ExpenseKind::Ordinary,
                    (string) $current->title,
                    $current->notes,
                    $current->project_id,
                    $current->contract_id,
                    (int) $current->lock_version,
                ),
                [new SaveExpenseRowData(
                    (int) $row->getKey(),
                    (int) $row->position,
                    $row->vendor_id,
                    $row->type,
                    (string) $row->description,
                    null,
                    null,
                    $amount,
                    (bool) $row->amount_includes_vat,
                    (string) $row->vat_rate,
                    (bool) $row->is_extra,
                    $row->funded_plafond_expense_id,
                    $row->spend_date?->format('Y-m-d'),
                    $row->period_start?->format('Y-m-d'),
                    $row->period_end?->format('Y-m-d'),
                    $row->distribution,
                    $row->external_reference,
                    (int) $row->lock_version,
                    true,
                )],
                (string) str()->uuid(),
            );
        }

        $versionsBeforePrune = Version::query()->count();
        $this->assertCount(10, app(OperationalRevisionQuery::class)->visibleForRoot($context, $expense));
        $this->assertGreaterThan(0, app(ApplyOperationalRevisionRetention::class)->execute());
        (new Version)->pruneAll();

        $actual = app(HistoricalAnnualBudgetQuery::class)->execute(
            $actor,
            $context,
            (int) $year->getKey(),
            '2026-03-01T10:30:00Z',
        );
        $this->assertLessThan($versionsBeforePrune, Version::query()->count());
        $this->assertSame($expected['expenses'], $actual['expenses']);
        $this->assertSame($expected['summary'], $actual['summary']);
    }

    public function test_historical_summary_uses_the_canonical_plafond_projection_without_legacy_overrun_aliases(): void
    {
        [$actor, $context, $year, $plafond] = $this->fixture();
        $plafond->forceFill([
            'kind' => ExpenseKind::Plafond,
            'approved_amount' => '300.00',
            'approved_basis' => 'net',
            'current_planning_row_id' => null,
        ])->saveQuietly();
        $plafond->rows()->firstOrFail()->forceFill([
            'vendor_id' => null,
            'type' => ExpenseType::AllocationAdjustment,
            'created_by_user_id' => $actor->getKey(),
            'entered_amount' => '300.00',
            'net_amount' => '300.00',
            'vat_amount' => '66.00',
            'gross_amount' => '366.00',
            'spend_date' => '2026-01-01',
        ])->saveQuietly();
        $consumer = Expense::factory()->for($context->tenant)->create([
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $plafond->cost_center_id,
            'approved_amount' => '130.00',
            'approved_basis' => 'net',
        ]);
        $planned = ExpenseRow::factory()->for($consumer)->create([
            'tenant_id' => $context->tenantId,
            'type' => ExpenseType::Quote,
            'spend_date' => null,
            'net_amount' => '130.00',
            'vat_amount' => '28.60',
            'gross_amount' => '158.60',
            'funded_plafond_expense_id' => $plafond->getKey(),
        ]);
        ExpenseRow::factory()->for($consumer)->create([
            'tenant_id' => $context->tenantId,
            'position' => 2,
            'type' => ExpenseType::Actual,
            'spend_date' => '2026-06-01',
            'net_amount' => '140.00',
            'vat_amount' => '30.80',
            'gross_amount' => '170.80',
            'funded_plafond_expense_id' => $plafond->getKey(),
        ]);
        $consumer->forceFill(['current_planning_row_id' => $planned->getKey()])->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());

        $result = app(HistoricalAnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey(), '2026-03-01T10:30:00Z');

        $this->assertSame($result['totals']['current_planning']['official'], $result['summary']['proposed']);
        $this->assertSame($result['totals']['actual']['official'], $result['summary']['actual']);
        $this->assertArrayNotHasKey('plafond_overrun', $result['summary']);
        $this->assertSame('300.00', $result['plafonds'][0]['measures']['allocation']['official']);
        $this->assertSame('140.00', $result['plafonds'][0]['measures']['consumed']['official']);
    }

    public function test_historical_projection_includes_rows_and_linked_contract_term_context(): void
    {
        [$actor, $context, $year, $expense] = $this->fixture();
        $vendor = Vendor::factory()->for($context->tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $context->tenantId,
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $expense->cost_center_id,
            'title' => 'Historical contract',
            'active' => true,
            'lock_version' => 1,
        ]);
        $term = ContractTerm::query()->create([
            'tenant_id' => $context->tenantId,
            'contract_id' => $contract->getKey(),
            'source_rule_key' => (string) str()->uuid(),
            'effective_start' => '2026-01-01',
            'effective_end' => '2026-12-31',
            'billing_cycle' => BillingCycle::Annual,
            'entered_amount' => '100.00',
            'amount_includes_vat' => false,
            'vat_rate' => '22.00',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'auto_renew' => false,
            'lock_version' => 1,
        ]);
        $expense->forceFill(['contract_id' => $contract->getKey()])->saveQuietly();
        $expense->rows()->firstOrFail()->forceFill([
            'vendor_id' => $vendor->getKey(),
            'contract_term_id' => $term->getKey(),
        ])->saveQuietly();
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:10:00', 'UTC'));
        app(ApplyBudgetApproval::class)->execute(
            $actor,
            $context,
            $year->fresh(),
            new ApplyApprovalData(2, '2026-02-20', 'Historical approval', [
                new ApprovalChangeData((int) $expense->getKey(), 1, '80.00'),
            ]),
            (string) str()->uuid(),
        );

        $result = app(HistoricalAnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey(), '2026-03-01T10:30:00Z');

        $this->assertSame($expense->rows()->firstOrFail()->getKey(), $result['expenses'][0]['rows'][0]['id']);
        $this->assertSame($contract->getKey(), $result['historical_context']['contracts'][0]['id']);
        $this->assertSame($contract->getKey(), $result['historical_context']['contract_terms'][0]['contract_id']);
        $this->assertSame($term->getKey(), $result['historical_context']['contract_terms'][0]['id']);
        $this->assertSame($vendor->getKey(), $result['historical_context']['vendors'][0]['id']);
        $this->assertSame('80.00', $result['historical_context']['approval_operations'][0]['items'][0]['new_amount']);
    }

    /** @return array{User, TenantContext, PlanningYear, Expense} */
    private function fixture(string $timezone = 'UTC'): array
    {
        $tenant = Tenant::factory()->create(['timezone' => $timezone]);
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey()]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
            'spend_date' => null,
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        return [$actor, new TenantContext($tenant, $actor), $year, $expense->fresh()];
    }
}
