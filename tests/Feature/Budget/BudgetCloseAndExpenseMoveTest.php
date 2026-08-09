<?php

namespace Tests\Feature\Budget;

use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\MoveExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseClosureOutcome;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BudgetCloseAndExpenseMoveTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_move_closes_origin_and_copies_only_planning_context_to_the_target_year(): void
    {
        [$actor, $context, $sourceYear, $targetYear, $expense] = $this->fixture();
        $selected = $expense->rows->firstWhere('type', ExpenseType::Quote);

        [$origin, $destination] = app(MoveExpense::class)->execute(
            $actor,
            $context,
            $expense,
            1,
            (int) $targetYear->getKey(),
            (string) str()->uuid(),
        );

        $this->assertSame('closed', $origin->state->value);
        $this->assertSame(ExpenseClosureOutcome::Moved, $origin->closure_outcome);
        $this->assertSame($sourceYear->getKey(), $origin->planning_year_id);
        $this->assertSame('80.00', $origin->approved_amount);
        $this->assertCount(3, $origin->rows);

        $this->assertSame($targetYear->getKey(), $destination->planning_year_id);
        $this->assertSame($origin->getKey(), $destination->moved_from_expense_id);
        $this->assertSame('open', $destination->state->value);
        $this->assertNull($destination->approved_amount);
        $this->assertCount(2, $destination->rows);
        $this->assertFalse($destination->rows->contains('type', ExpenseType::Actual));
        $this->assertSame('120.00', $destination->currentPlanningRow?->net_amount);
        $this->assertSame($selected?->description, $destination->currentPlanningRow?->description);
        $this->assertTrue($destination->rows->every(fn (ExpenseRow $row): bool => ! $row->is_system_managed && $row->source_key === null));
        $this->assertDatabaseHas('expense_rows', ['expense_id' => $origin->getKey(), 'type' => 'actual', 'net_amount' => '30.00']);
        $this->assertDatabaseMissing('expense_rows', ['expense_id' => $destination->getKey(), 'type' => 'actual']);
    }

    public function test_next_year_credit_creates_a_linked_negative_actual_and_preserves_the_origin(): void
    {
        [$actor, $context, , $targetYear, $origin] = $this->fixture();
        $vendor = $origin->rows->first()?->vendor;
        $originActual = $origin->rows->firstWhere('type', ExpenseType::Actual)?->net_amount;

        $credit = app(CreateExpense::class)->execute(
            $actor,
            $context,
            new SaveExpenseData(
                (int) $targetYear->getKey(),
                (int) $origin->cost_center_id,
                ExpenseKind::Ordinary,
                'Nota di credito anno successivo',
                null,
                null,
                null,
                null,
                (int) $origin->getKey(),
            ),
            [new SaveExpenseRowData(
                null,
                1,
                (int) $vendor?->getKey(),
                ExpenseType::Actual,
                'Nota di credito ricevuta',
                null,
                null,
                '-20.00',
                false,
                '22.000000',
                false,
                null,
                '2027-01-10',
                null,
                null,
                null,
                null,
                null,
            )],
            (string) str()->uuid(),
        );

        $this->assertSame($origin->getKey(), $credit->credit_for_expense_id);
        $this->assertSame($targetYear->getKey(), $credit->planning_year_id);
        $this->assertSame('-20.00', $credit->rows->sole()->net_amount);
        $this->assertSame($originActual, $origin->fresh('rows')->rows->firstWhere('type', ExpenseType::Actual)?->net_amount);
        $this->assertNull($origin->fresh()->credit_for_expense_id);
    }

    public function test_stale_move_rolls_back_without_closing_or_creating_an_expense(): void
    {
        [$actor, $context, , $targetYear, $expense] = $this->fixture();
        $before = Expense::query()->count();

        try {
            app(MoveExpense::class)->execute(
                $actor,
                $context,
                $expense,
                99,
                (int) $targetYear->getKey(),
                (string) str()->uuid(),
            );
            $this->fail('A stale move must fail.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }

        $this->assertSame($before, Expense::query()->count());
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->getKey(),
            'state' => 'open',
            'closure_outcome' => null,
            'lock_version' => 1,
        ]);
    }

    public function test_move_expense_audit_failure_rolls_back_both_years_and_the_revision_batch(): void
    {
        [$actor, $context, , $targetYear, $expense] = $this->fixture();
        $correlationId = (string) str()->uuid();
        $expenseCount = Expense::query()->count();
        $auditCount = AuditEvent::query()->count();
        $failure = static fn (): never => throw new RuntimeException('forced audit failure');
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, $failure);

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(MoveExpense::class));
            $action = app(MoveExpense::class);
            $action->execute(
                $actor,
                $context,
                $expense,
                1,
                (int) $targetYear->getKey(),
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', [
                'id' => $expense->getKey(),
                'state' => 'open',
                'closure_outcome' => null,
                'lock_version' => 1,
            ]);
            $this->assertDatabaseMissing('expenses', ['moved_from_expense_id' => $expense->getKey()]);
            $this->assertDatabaseMissing('revision_batches', ['correlation_id' => $correlationId]);
            $this->assertDatabaseCount('expenses', $expenseCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User, TenantContext, PlanningYear, PlanningYear, Expense} */
    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $sourceYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $targetYear = PlanningYear::factory()->for($tenant)->create(['year_label' => 2027]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $sourceYear->getKey(),
            'cost_center_id' => $center->getKey(),
            'approved_amount' => '80.00',
            'approved_basis' => 'net',
        ]);
        $estimate = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'position' => 1,
            'type' => ExpenseType::Estimate,
            'description' => 'Estimate option',
            'spend_date' => null,
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
        ]);
        $quote = ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'position' => 2,
            'type' => ExpenseType::Quote,
            'description' => 'Selected quote',
            'spend_date' => null,
            'quantity' => null,
            'unit_price' => null,
            'entered_amount' => '120.000000',
            'net_amount' => '120.00',
            'vat_amount' => '26.40',
            'gross_amount' => '146.40',
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $vendor->getKey(),
            'position' => 3,
            'type' => ExpenseType::Actual,
            'description' => 'Paid in origin year',
            'spend_date' => '2026-06-01',
            'entered_amount' => '30.000000',
            'net_amount' => '30.00',
            'vat_amount' => '6.60',
            'gross_amount' => '36.60',
        ]);
        $expense->forceFill(['current_planning_row_id' => $quote->getKey()])->saveQuietly();

        return [$actor, new TenantContext($tenant, $actor), $sourceYear, $targetYear, $expense->fresh(['rows', 'currentPlanningRow'])];
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
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

    /** @param array<int, mixed> $listeners */
    private function restoreAuditCreatingListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);
        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
