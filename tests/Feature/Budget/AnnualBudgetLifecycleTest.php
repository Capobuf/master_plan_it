<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Enums\BudgetState;
use App\Domain\Contracts\Actions\GenerateContractOccurrenceForYear;
use App\Domain\Contracts\Enums\BillingCycle;
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

    public function test_preparation_cannot_transition_directly_to_closed_and_legacy_actions_are_absent(): void
    {
        [, , $year] = $this->context();
        try {
            $year->forceFill(['budget_state' => BudgetState::Closed])->save();
            $this->fail('Preparation to Closed belongs to Slice 026 and must be rejected.');
        } catch (\DomainException $exception) {
            $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
        }
        $this->assertDatabaseHas('planning_years', [
            'id' => $year->getKey(), 'budget_state' => 'preparation', 'lock_version' => 1,
        ]);
        try {
            PlanningYear::query()->whereKey($year->getKey())->update(['budget_state' => BudgetState::Closed]);
            $this->fail('Builder updates must not bypass the budget state lifecycle.');
        } catch (\DomainException $exception) {
            $this->assertSame('BUDGET_STATE_CONFLICT', $exception->getMessage());
        }
        $this->assertSame(1, PlanningYear::query()->whereKey($year->getKey())->update(['year_label' => 2027]));
        $this->assertFalse(method_exists($year, 'closeBudget'));
        $this->assertFalse(method_exists($year, 'reopenBudget'));
        $this->assertFalse(class_exists('App\\Domain\\Budget\\Actions\\CloseAnnualBudget', false));
        $this->assertFalse(class_exists('App\\Models\\ApprovalOperation', false));
    }

    public function test_contract_generates_one_selected_annual_planning_expense_with_project(): void
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
        $this->assertSame('quote', $expense->rows->first()->type instanceof ExpenseType
            ? $expense->rows->first()->type->value
            : $expense->rows->first()->type);
        $this->assertSame('1200.00', $expense->rows->first()->net_amount);
        $this->assertSame($expense->rows->first()->getKey(), $expense->current_planning_row_id);
        $this->assertDatabaseMissing('expense_rows', ['expense_id' => $expense->getKey(), 'type' => 'actual']);
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
