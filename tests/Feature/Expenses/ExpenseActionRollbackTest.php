<?php

namespace Tests\Feature\Expenses;

use App\Domain\Expenses\Actions\BulkExpenseAction;
use App\Domain\Expenses\Actions\CloseExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\DeleteExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Data\SaveExpenseData;
use App\Domain\Expenses\Data\SaveExpenseRowData;
use App\Domain\Expenses\Enums\ExpenseKind;
use App\Domain\Expenses\Enums\ExpenseState;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\Expense;
use App\Models\PlanningYear;
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

final class ExpenseActionRollbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_create_expense_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->administratorContext();
        $data = $this->expenseData($year, $center, 'Rollback create');
        $rows = [$this->rowData($vendor, ExpenseType::Estimate)];
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CreateExpense::class));
            $action = app(CreateExpense::class);
            $action->execute($actor, $context, $data, $rows, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('expenses', ['tenant_id' => $context->tenantId, 'title' => 'Rollback create']);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_update_expense_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->administratorContext();
        $expense = $this->existingExpense($actor, $context, $year, $center, $vendor);
        $data = $this->expenseData($year, $center, 'Rollback update', $expense->lock_version);
        $rows = [$this->rowData($vendor, ExpenseType::Estimate, $expense->rows()->firstOrFail()->getKey(), 1)];
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(UpdateExpense::class));
            $action = app(UpdateExpense::class);
            $action->execute($actor, $context, $expense, $data, $rows, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'title' => 'Rollback expense', 'lock_version' => 1]);
            $this->assertDatabaseMissing('expenses', ['id' => $expense->getKey(), 'title' => 'Rollback update']);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_expense_audit_failure_rolls_back_expense_and_audit(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->administratorContext();
        $expense = $this->existingExpense($actor, $context, $year, $center, $vendor);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(DeleteExpense::class));
            $action = app(DeleteExpense::class);
            $action->execute($actor, $context, $expense, $expense->lock_version, false, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'deleted_at' => null]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_close_expense_audit_failure_rolls_back_lifecycle_and_audit(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->administratorContext();
        $expense = $this->existingExpense($actor, $context, $year, $center, $vendor, ExpenseType::Actual);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners($correlationId, static fn (): never => throw new RuntimeException('forced audit failure'));

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CloseExpense::class));
            $action = app(CloseExpense::class);
            $action->execute($actor, $context, $expense, $expense->lock_version, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'state' => ExpenseState::Open->value, 'lock_version' => 1]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_bulk_expense_audit_failure_rolls_back_every_selected_expense(): void
    {
        [$actor, $context, $year, $center, $vendor] = $this->administratorContext();
        $first = $this->existingExpense($actor, $context, $year, $center, $vendor);
        $second = $this->existingExpense($actor, $context, $year, $center, $vendor);
        $auditCount = AuditEvent::query()->count();
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;
        $listeners = $dispatcher->getRawListeners()[$eventName] ?? [];
        $seen = 0;
        $dispatcher->listen($eventName, static function () use (&$seen): void {
            if (++$seen === 2) {
                throw new RuntimeException('forced second bulk audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(BulkExpenseAction::class));
            $action = app(BulkExpenseAction::class);
            $action->execute(
                $actor,
                $context,
                'close',
                (int) $year->getKey(),
                [
                    ['id' => (int) $first->getKey(), 'lock_version' => 1],
                    ['id' => (int) $second->getKey(), 'lock_version' => 1],
                ],
                (string) str()->uuid(),
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', ['id' => $first->getKey(), 'state' => 'open', 'lock_version' => 1]);
            $this->assertDatabaseHas('expenses', ['id' => $second->getKey(), 'state' => 'open', 'lock_version' => 1]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User,TenantContext,PlanningYear,CostCenter,Vendor} */
    private function administratorContext(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $center = CostCenter::factory()->for($tenant)->create();
        $vendor = Vendor::factory()->for($tenant)->create();

        return [$actor, new TenantContext($tenant, $actor), $year, $center, $vendor];
    }

    private function existingExpense(User $actor, TenantContext $context, PlanningYear $year, CostCenter $center, Vendor $vendor, ExpenseType $type = ExpenseType::Estimate): Expense
    {
        return app(CreateExpense::class)->execute(
            $actor,
            $context,
            $this->expenseData($year, $center, 'Rollback expense'),
            [$this->rowData($vendor, $type)],
            (string) str()->uuid(),
        );
    }

    private function expenseData(PlanningYear $year, CostCenter $center, string $title, ?int $expectedLockVersion = null): SaveExpenseData
    {
        return new SaveExpenseData($year->getKey(), $center->getKey(), ExpenseKind::Ordinary, $title, null, null, null, $expectedLockVersion);
    }

    private function rowData(Vendor $vendor, ExpenseType $type, ?int $id = null, ?int $expectedLockVersion = null): SaveExpenseRowData
    {
        return new SaveExpenseRowData($id, 1, $vendor->getKey(), $type, 'Rollback row', null, null, '100.00', false, '22.00', false, null, '2026-01-15', null, null, null, null, $expectedLockVersion);
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
