<?php

namespace Tests\Feature\Budget;

use App\Domain\Budget\Actions\ApproveBudgetProposal;
use App\Domain\Budget\Data\ApproveBudgetProposalData;
use App\Domain\Budget\Queries\BudgetApprovalPreviewQuery;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\BudgetApproval;
use App\Models\BudgetApprovalItem;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class BudgetProposalApprovalRollbackTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    #[DataProvider('failureCheckpointProvider')]
    public function test_every_approval_failure_checkpoint_rolls_back_header_items_state_base_revision_and_audits(
        string $modelClass,
        string $checkpoint,
    ): void {
        $tenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'entered_amount' => '100.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $secondExpense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $secondRow = ExpenseRow::factory()->for($secondExpense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'entered_amount' => '50.00', 'net_amount' => '50.00', 'vat_amount' => '11.00', 'gross_amount' => '61.00',
        ]);
        $secondExpense->forceFill(['current_planning_row_id' => $secondRow->getKey()])->saveQuietly();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $correlationId = (string) str()->uuid();
        $before = $this->effects($tenant, $year);
        $approvalCount = BudgetApproval::query()->count();
        $failure = static fn (): never => throw new RuntimeException('forced '.$checkpoint.' failure');
        [$dispatcher, $event, $listeners] = $this->injectFailure($modelClass, $checkpoint, $failure);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forced '.$checkpoint.' failure');
        try {
            self::assertTrue(class_exists(ApproveBudgetProposal::class));
            $action = app(ApproveBudgetProposal::class);
            $action->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                $correlationId,
            );
        } finally {
            $this->restoreListeners($dispatcher, $event, $listeners);
            $this->assertSame($before, $this->effects($tenant->fresh(), $year->fresh()));
            $this->assertDatabaseMissing('budget_approvals', ['correlation_id' => $correlationId]);
            $this->assertDatabaseMissing('revision_batches', ['correlation_id' => $correlationId]);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
            $this->assertDatabaseCount('budget_approvals', $approvalCount);
        }
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function failureCheckpointProvider(): iterable
    {
        yield 'approval header' => [BudgetApproval::class, 'header'];
        yield 'snapshot item' => [BudgetApprovalItem::class, 'item'];
        yield 'Budget state' => [PlanningYear::class, 'state'];
        yield 'Revision link' => [RevisionBatchItem::class, 'revision-link'];
        yield 'first Base lock' => [Tenant::class, 'base-lock'];
        yield 'business Audit' => [AuditEvent::class, 'business-audit'];
    }

    public function test_late_audit_failure_preserves_a_preexisting_irreversible_base_lock(): void
    {
        $lockedAt = now()->subYear()->utc()->startOfSecond();
        $tenant = Tenant::factory()->create(['economic_basis_locked_at' => $lockedAt]);
        $actor = $this->tenantUser($tenant);
        $context = new TenantContext($tenant, $actor);
        $year = PlanningYear::factory()->for($tenant)->create();
        $center = CostCenter::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(), 'cost_center_id' => $center->getKey(),
        ]);
        $row = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(), 'type' => ExpenseType::Quote,
            'entered_amount' => '100.00', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
        ]);
        $expense->forceFill(['current_planning_row_id' => $row->getKey()])->saveQuietly();
        $preview = app(BudgetApprovalPreviewQuery::class)->execute($actor, $context, (int) $year->getKey());
        $yearVersionCount = $year->versions()->count();
        $failure = static fn (): never => throw new RuntimeException('forced business-audit failure');
        [$dispatcher, $event, $listeners] = $this->injectFailure(AuditEvent::class, 'business-audit', $failure);

        try {
            app(ApproveBudgetProposal::class)->execute(
                $actor,
                $context,
                $year,
                ApproveBudgetProposalData::fromEvidence('2026-08-13', null, $preview->proposal->composition),
                (string) str()->uuid(),
            );
            $this->fail('Late Audit failure did not abort approval.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced business-audit failure', $exception->getMessage());
        } finally {
            $this->restoreListeners($dispatcher, $event, $listeners);
        }

        $this->assertSame($lockedAt->toISOString(), $tenant->fresh()->economic_basis_locked_at?->toISOString());
        $this->assertSame('preparation', $year->fresh()->budget_state->value);
        $this->assertSame($yearVersionCount, $year->versions()->count());
        $this->assertDatabaseCount('budget_approvals', 0);
    }

    /** @return array<string, int|string|null> */
    private function effects(Tenant $tenant, PlanningYear $year): array
    {
        return [
            'state' => $year->budget_state->value,
            'version' => (int) $year->lock_version,
            'base_lock' => $tenant->economic_basis_locked_at?->toISOString(),
            'approvals' => BudgetApproval::query()->where('tenant_id', $tenant->getKey())->count(),
            'items' => BudgetApprovalItem::query()->where('tenant_id', $tenant->getKey())->count(),
            'revision_batches' => RevisionBatch::query()->where('tenant_id', $tenant->getKey())->count(),
            'revision_items' => RevisionBatchItem::query()->where('tenant_id', $tenant->getKey())->count(),
            'year_versions' => $year->versions()->count(),
            'audits' => AuditEvent::query()->where('tenant_id', $tenant->getKey())->count(),
        ];
    }

    /** @param class-string $modelClass @return array{Dispatcher,string,array<int,mixed>} */
    private function injectFailure(string $modelClass, string $checkpoint, \Closure $failure): array
    {
        $dispatcher = $modelClass::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $verb = in_array($checkpoint, ['state', 'base-lock'], true) ? 'updated' : 'creating';
        $event = 'eloquent.'.$verb.': '.$modelClass;
        $listeners = $dispatcher->getRawListeners()[$event] ?? [];
        $seenSnapshotItems = 0;
        $dispatcher->listen($event, static function (object $model) use ($checkpoint, $failure, &$seenSnapshotItems): void {
            $matches = match ($checkpoint) {
                'state' => $model instanceof PlanningYear && $model->budget_state->value === 'approved',
                'base-lock' => $model instanceof Tenant && $model->economic_basis_locked_at !== null,
                'business-audit' => $model instanceof AuditEvent && $model->event_type === 'budget.approved',
                'item' => $model instanceof BudgetApprovalItem && ++$seenSnapshotItems === 2,
                default => true,
            };
            if ($matches) {
                $failure();
            }
        });

        return [$dispatcher, $event, $listeners];
    }

    /** @param array<int,mixed> $listeners */
    private function restoreListeners(Dispatcher $dispatcher, string $event, array $listeners): void
    {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
}
