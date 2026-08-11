<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Actions\CloseAnnualBudget;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Budget\Queries\AnnualBudgetQuery;
use App\Domain\Budget\Queries\HistoricalAnnualBudgetQuery;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Enums\BillingCycle;
use App\Domain\Expenses\Actions\CloseExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Revisions\Actions\ActivateAnnualHistory;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AnnualBudgetLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_approval_budget_and_expense_closure_are_independent_and_economic_changes_reopen_expense(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        [$expense, $row] = $this->expense($context->tenant, $year, $center, $vendor, '100.00');

        $operation = app(ApplyBudgetApproval::class)->execute($actor, $context, $year, new ApplyApprovalData(
            1,
            '2026-02-01',
            'Initial approval',
            [new ApprovalChangeData((int) $expense->getKey(), 1, '90.00')],
        ), (string) str()->uuid());

        $this->assertSame('initial', $operation->kind->value);
        $this->assertSame('approved', $year->fresh()->budget_state->value);
        $this->assertSame('90.00', $expense->fresh()->approved_amount);

        app(CloseAnnualBudget::class)->execute($actor, $context, $year->fresh(), 2, (string) str()->uuid());
        $closed = app(CloseExpense::class)->execute($actor, $context, $expense->fresh(), 2, null, (string) str()->uuid());
        $this->assertSame('closed', $closed->state->value);

        app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $closed,
            new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, $expense->title, null, null, null, 3),
            [$this->rowData($vendor, '120.00', (int) $row->getKey(), 1, true)],
            (string) str()->uuid(),
        );

        $reopened = $expense->fresh();
        $this->assertSame('open', $reopened->state->value);
        $this->assertSame('closed', $year->fresh()->budget_state->value);
        $this->assertSame('BUDGET_CLOSED', app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey())['budget']['warning']);
    }

    public function test_generic_update_cannot_reallocate_dimensions_of_an_approved_expense(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        [$expense, $row] = $this->expense($context->tenant, $year, $center, $vendor, '100.00');
        app(ApplyBudgetApproval::class)->execute($actor, $context, $year, new ApplyApprovalData(
            1,
            '2026-02-01',
            null,
            [new ApprovalChangeData((int) $expense->getKey(), 1, '90.00')],
        ), (string) str()->uuid());
        $otherCenter = CostCenter::factory()->for($context->tenant)->create();

        try {
            app(UpdateExpense::class)->execute(
                $actor,
                $context,
                $expense->fresh(),
                new SaveExpenseData((int) $year->getKey(), (int) $otherCenter->getKey(), ExpenseKind::Ordinary, $expense->title, null, null, null, 2),
                [$this->rowData($vendor, '100.00', (int) $row->getKey(), 1, true)],
                (string) str()->uuid(),
            );
            $this->fail('An approved dimension reallocation must use the approval endpoint.');
        } catch (\DomainException $exception) {
            $this->assertSame('APPROVED_DIMENSION_REALLOCATION_REQUIRED', $exception->getMessage());
        }

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->getKey(),
            'cost_center_id' => $center->getKey(),
            'approved_amount' => '90.00',
            'lock_version' => 2,
        ]);
    }

    public function test_history_uses_batch_cutoff_and_rejects_pre_activation_time(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        [$expense, $row] = $this->expense($context->tenant, $year, $center, $vendor, '100.00');
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00', 'UTC'));
        app(ActivateAnnualHistory::class)->execute($actor, $context, $year, (string) str()->uuid());

        $this->travelTo(CarbonImmutable::parse('2026-03-01 11:00:00', 'UTC'));
        app(UpdateExpense::class)->execute(
            $actor,
            $context,
            $expense,
            new SaveExpenseData((int) $year->getKey(), (int) $center->getKey(), ExpenseKind::Ordinary, $expense->title, null, null, null, 1),
            [$this->rowData($vendor, '150.00', (int) $row->getKey(), 1, true)],
            (string) str()->uuid(),
        );

        $before = app(HistoricalAnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey(), '2026-03-01T10:30:00Z');
        $after = app(HistoricalAnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey(), '2026-03-01T11:30:00Z');
        $this->assertSame('100.00', $before['expenses'][0]['planned']);
        $this->assertSame('150.00', $after['expenses'][0]['planned']);
        $this->assertTrue($before['read_only']);

        $this->expectExceptionMessage('HISTORY_BEFORE_ACTIVATION');
        app(HistoricalAnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey(), '2026-03-01T09:59:59Z');
    }

    public function test_contract_generates_one_unselected_annual_planning_expense_with_project(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        $project = Project::factory()->for($context->tenant)->create();
        $contract = Contract::query()->create(['tenant_id' => $context->tenantId, 'vendor_id' => $vendor->getKey(), 'cost_center_id' => $center->getKey(),
            'project_id' => $project->getKey(), 'title' => 'Annual service', 'active' => true, 'lock_version' => 1]);
        ContractTerm::query()->create(['tenant_id' => $context->tenantId, 'contract_id' => $contract->getKey(), 'source_rule_key' => (string) str()->uuid(),
            'effective_start' => '2026-01-01', 'effective_end' => '2026-12-31', 'billing_cycle' => BillingCycle::Monthly,
            'entered_amount' => '100.00', 'amount_includes_vat' => false, 'vat_rate' => '22.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00', 'auto_renew' => false, 'lock_version' => 1]);

        $expense = app(GenerateContractOccurrenceForYear::class)->execute($actor, $context, $contract, 2026, (string) str()->uuid());

        $this->assertSame($project->getKey(), $expense->project_id);
        $this->assertCount(1, $expense->rows);
        $this->assertSame('quote', $expense->rows->first()->type->value);
        $this->assertSame('1200.00', $expense->rows->first()->net_amount);
        $this->assertNull($expense->current_planning_row_id);
        $this->assertDatabaseMissing('expense_rows', ['expense_id' => $expense->getKey(), 'type' => 'actual']);
    }

    public function test_plafond_remains_distinguishable_and_consumption_overrun_is_reported(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        [$plafond] = $this->expense($context->tenant, $year, $center, $vendor, '1000.00', ExpenseKind::Plafond);
        [$consumer] = $this->expense($context->tenant, $year, $center, $vendor, '1200.00');
        $consumer->currentPlanningRow->forceFill(['funded_plafond_expense_id' => $plafond->getKey()])->save();
        ExpenseRow::factory()->for($consumer)->create(['tenant_id' => $context->tenantId, 'type' => ExpenseType::Actual, 'spend_date' => '2026-06-01',
            'entered_amount' => '1200.00', 'net_amount' => '1200.00', 'vat_amount' => '264.00', 'gross_amount' => '1464.00']);

        $result = app(AnnualBudgetQuery::class)->execute($actor, $context, (int) $year->getKey());
        $this->assertSame(['ordinary', 'plafond'], collect($result['expenses'])->pluck('kind')->sort()->values()->all());
        $this->assertSame('1200.00', $result['summary']['proposed']);
        $this->assertSame('200.00', $result['summary']['plafond_overrun']);
    }

    public function test_apply_budget_approval_audit_failure_rolls_back_the_whole_operation(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        [$expense] = $this->expense($context->tenant, $year, $center, $vendor, '100.00');
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $failure = static fn (): never => throw new RuntimeException('forced audit failure');
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, $failure);

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(ApplyBudgetApproval::class));
            $action = app(ApplyBudgetApproval::class);
            $action->execute($actor, $context, $year, new ApplyApprovalData(
                1,
                '2026-02-01',
                'Rollback approval',
                [new ApprovalChangeData((int) $expense->getKey(), 1, '90.00')],
            ), $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('planning_years', ['id' => $year->getKey(), 'budget_state' => 'preparation', 'lock_version' => 1]);
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'approved_amount' => null, 'lock_version' => 1]);
            $this->assertDatabaseMissing('approval_operations', ['correlation_id' => $correlationId]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_close_annual_budget_audit_failure_rolls_back_state_and_revision(): void
    {
        [$actor, $context, $year] = $this->context();
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $failure = static fn (): never => throw new RuntimeException('forced audit failure');
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, $failure);

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CloseAnnualBudget::class));
            $action = app(CloseAnnualBudget::class);
            $action->execute($actor, $context, $year, 1, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('planning_years', ['id' => $year->getKey(), 'budget_state' => 'preparation', 'lock_version' => 1]);
            $this->assertDatabaseMissing('revision_batches', ['correlation_id' => $correlationId]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_activate_annual_history_audit_failure_rolls_back_baseline_and_activation(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->context();
        $this->expense($context->tenant, $year, $center, $vendor, '100.00');
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $failure = static fn (): never => throw new RuntimeException('forced audit failure');
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, $failure);

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(ActivateAnnualHistory::class));
            $action = app(ActivateAnnualHistory::class);
            $action->execute($actor, $context, $year, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('planning_years', ['id' => $year->getKey(), 'history_activated_at' => null, 'lock_version' => 1]);
            $this->assertDatabaseMissing('revision_batches', ['correlation_id' => $correlationId]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User,TenantContext,PlanningYear,CostCenter,Vendor} */
    private function context(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor), $year, $center, $vendor];
    }

    /** @return array{Expense,ExpenseRow} */
    private function expense(Tenant $tenant, PlanningYear $year, CostCenter $center, Vendor $vendor, string $amount, ExpenseKind $kind = ExpenseKind::Ordinary): array
    {
        $expense = Expense::factory()->for($tenant)->create(['planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(), 'kind' => $kind]);
        $row = ExpenseRow::factory()->for($expense)->create(['tenant_id' => $tenant->getKey(), 'vendor_id' => $vendor->getKey(), 'type' => ExpenseType::Estimate,
            'spend_date' => null, 'entered_amount' => $amount, 'net_amount' => $amount, 'vat_amount' => bcmul($amount, '0.22', 2), 'gross_amount' => bcmul($amount, '1.22', 2)]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();

        return [$expense->fresh('currentPlanningRow'), $row];
    }

    private function rowData(Vendor $vendor, string $amount, int $id, int $lockVersion, bool $current): SaveExpenseRowData
    {
        return new SaveExpenseRowData($id, 1, (int) $vendor->getKey(), ExpenseType::Estimate, 'Plan', null, null, $amount, false, '22.00', false, null, null, null, null, null, null, $lockVersion, $current);
    }

    /** @return array{Dispatcher,string,array<int,mixed>} */
    private function auditCreatingListeners(string $correlationId, \Closure $failure): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $dispatcher->listen($eventName, static function (AuditEvent $event) use ($correlationId, $failure): void {
            if ($event->correlation_id === $correlationId) {
                $failure();
            }
        });

        return [$dispatcher, $eventName, $listeners];
    }

    /** @param array<int,mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);
        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
