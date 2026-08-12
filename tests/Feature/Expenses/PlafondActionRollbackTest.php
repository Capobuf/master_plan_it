<?php

namespace Tests\Feature\Expenses;

use App\Domain\Plafonds\Actions\AddAllocationAdjustment;
use App\Domain\Plafonds\Actions\CreatePlafond;
use App\Domain\Plafonds\Data\AllocationAdjustmentData;
use App\Domain\Plafonds\Data\SavePlafondData;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PlafondActionRollbackTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionCatalogueSeeder::class)->run();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_create_plafond_audit_failure_rolls_back_root_row_revision_and_audit(): void
    {
        [$actor, $context, $year, $center] = $this->context();
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditFailure(
            $correlationId,
            static fn (): never => throw new RuntimeException('forced Plafond audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(CreatePlafond::class));
            $action = app(CreatePlafond::class);
            $action->execute(
                $actor,
                $context,
                new SavePlafondData(
                    (int) $year->getKey(),
                    (int) $center->getKey(),
                    'Rollback Plafond',
                    null,
                    $this->adjustment('3000.00'),
                ),
                $correlationId,
            );
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('expenses', [
                'tenant_id' => $context->tenantId,
                'title' => 'Rollback Plafond',
            ]);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_add_allocation_adjustment_audit_failure_rolls_back_row_version_revision_and_audit(): void
    {
        [$actor, $context, $year, $center] = $this->context();
        $plafond = app(CreatePlafond::class)->execute(
            $actor,
            $context,
            new SavePlafondData(
                (int) $year->getKey(),
                (int) $center->getKey(),
                'Stable Plafond',
                null,
                $this->adjustment('3000.00'),
            ),
            (string) str()->uuid(),
        );
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $rowCount = ExpenseRow::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditFailure(
            $correlationId,
            static fn (): never => throw new RuntimeException('forced Plafond audit failure'),
        );

        try {
            $this->expectException(RuntimeException::class);
            self::assertTrue(class_exists(AddAllocationAdjustment::class));
            $action = app(AddAllocationAdjustment::class);
            $action->execute(
                $actor,
                $context,
                $plafond,
                1,
                $this->adjustment('1000.00'),
                $correlationId,
            );
        } finally {
            $this->restoreListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('expenses', [
                'id' => $plafond->getKey(),
                'lock_version' => 1,
            ]);
            $this->assertDatabaseCount('expense_rows', $rowCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{User,TenantContext,PlanningYear,CostCenter} */
    private function context(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PlatformAdministrator::class)->assign($actor);

        return [
            $actor,
            new TenantContext($tenant, $actor),
            PlanningYear::factory()->for($tenant)->create(),
            CostCenter::factory()->for($tenant)->create(),
        ];
    }

    private function adjustment(string $amount): AllocationAdjustmentData
    {
        return new AllocationAdjustmentData(
            'Adjustment',
            null,
            null,
            null,
            $amount,
            false,
            '22.00',
            '2026-08-12',
        );
    }

    /** @return array{Dispatcher,string,array<int,mixed>} */
    private function auditFailure(string $correlationId, \Closure $failure): array
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
    private function restoreListeners(Dispatcher $dispatcher, string $eventName, array $listeners): void
    {
        $dispatcher->forget($eventName);
        foreach ($listeners as $listener) {
            $dispatcher->listen($eventName, $listener);
        }
    }
}
