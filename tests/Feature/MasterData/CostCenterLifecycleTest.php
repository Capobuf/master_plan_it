<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateCostCenter;
use App\Domain\MasterData\Actions\DeactivateCostCenter;
use App\Domain\MasterData\Actions\DeleteCostCenter;
use App\Domain\MasterData\Actions\ReactivateCostCenter;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CostCenterLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    public function test_deactivation_rejects_active_descendants_and_lifecycle_is_optimistic_and_audited(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.deactivate', 'cost-center.reactivate', 'cost-center.delete');
        $create = app(CreateCostCenter::class);
        $parent = $create->execute($actor, $context, 'Parent', null, 'create-parent');
        $create->execute($actor, $context, 'Active child', $parent, 'create-child');

        try {
            app(DeactivateCostCenter::class)->execute($actor, $context, $parent, 1, 'blocked-parent');
            $this->fail('A parent with active descendants was deactivated.');
        } catch (DomainException $exception) {
            $this->assertSame('ACTIVE_DESCENDANT_EXISTS', $exception->getMessage());
        }

        $inactiveParent = $create->execute($actor, $context, 'Inactive descendant parent', null, 'create-inactive-parent');
        $inactiveChild = $create->execute($actor, $context, 'Inactive descendant child', $inactiveParent, 'create-inactive-child');
        CostCenter::query()->whereKey($inactiveChild->getKey())->update(['active' => false]);
        try {
            app(DeleteCostCenter::class)->execute($actor, $context, $inactiveParent, 1, 'delete-inactive-descendant-parent');
            $this->fail('A cost center with an inactive descendant was deleted.');
        } catch (DomainException $exception) {
            $this->assertSame('REFERENCED_RECORD_DELETE_DENIED', $exception->getMessage());
        }

        $leaf = $create->execute($actor, $context, 'Lifecycle leaf', null, 'create-leaf');
        $deactivated = app(DeactivateCostCenter::class)->execute($actor, $context, $leaf, 1, 'deactivate-leaf');
        $this->assertFalse($deactivated->active);
        $this->assertSame(2, $deactivated->lock_version);

        try {
            app(ReactivateCostCenter::class)->execute($actor, $context, $deactivated, 1, 'stale-reactivate');
            $this->fail('A stale lifecycle request overwrote the cost center.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }

        $reactivated = app(ReactivateCostCenter::class)->execute($actor, $context, $deactivated, 2, 'reactivate-leaf');
        $this->assertTrue($reactivated->active);
        $this->assertSame(3, $reactivated->lock_version);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'cost-center.deactivated', 'correlation_id' => 'deactivate-leaf']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'cost-center.reactivated', 'correlation_id' => 'reactivate-leaf']);
        $this->assertSame($tenant->getKey(), $reactivated->tenant_id);
    }

    public function test_delete_allows_only_an_unreferenced_leaf_and_preserves_audit_evidence(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.delete');
        $create = app(CreateCostCenter::class);
        $parent = $create->execute($actor, $context, 'Delete parent', null, 'create-parent');
        $create->execute($actor, $context, 'Delete child', $parent, 'create-child');

        try {
            app(DeleteCostCenter::class)->execute($actor, $context, $parent, 1, 'delete-parent');
            $this->fail('A cost center with descendants was deleted.');
        } catch (DomainException $exception) {
            $this->assertSame('REFERENCED_RECORD_DELETE_DENIED', $exception->getMessage());
        }

        $referenced = $create->execute($actor, $context, 'Referenced leaf', null, 'create-referenced');
        $year = PlanningYear::factory()->for($tenant)->create();
        DB::table('expenses')->insert([
            'tenant_id' => $tenant->getKey(),
            'planning_year_id' => $year->getKey(),
            'cost_center_id' => $referenced->getKey(),
            'kind' => 'ordinary',
            'title' => 'Historical expense',
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            app(DeleteCostCenter::class)->execute($actor, $context, $referenced, 1, 'delete-referenced');
            $this->fail('A referenced cost center was deleted.');
        } catch (DomainException $exception) {
            $this->assertSame('REFERENCED_RECORD_DELETE_DENIED', $exception->getMessage());
        }

        $leaf = $create->execute($actor, $context, 'Disposable leaf', null, 'create-disposable');
        $priorVersionId = $leaf->latestVersions()->firstOrFail()->getKey();
        $snapshotCreatedAfterSoftDelete = false;
        $versionDispatcher = Version::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $versionDispatcher);
        $versionEvent = 'eloquent.creating: '.Version::class;
        $versionListeners = $versionDispatcher->getRawListeners()[$versionEvent] ?? [];
        Version::creating(function (Version $version) use ($leaf, &$snapshotCreatedAfterSoftDelete): void {
            if ((string) $version->getAttribute('versionable_type') !== $leaf->getMorphClass()
                || (int) $version->getAttribute('versionable_id') !== (int) $leaf->getKey()) {
                return;
            }

            $snapshotCreatedAfterSoftDelete = CostCenter::query()->find($leaf->getKey()) === null
                && CostCenter::withTrashed()->find($leaf->getKey()) !== null;
        });
        try {
            app(DeleteCostCenter::class)->execute($actor, $context, $leaf, 1, 'delete-disposable');
        } finally {
            $versionDispatcher->forget($versionEvent);
            foreach ($versionListeners as $listener) {
                $versionDispatcher->listen($versionEvent, $listener);
            }
        }
        $this->assertNull(CostCenter::query()->find($leaf->getKey()));
        $this->assertNotNull(CostCenter::withTrashed()->find($leaf->getKey()));
        $this->assertDatabaseHas('audit_events', ['event_type' => 'cost-center.deleted', 'correlation_id' => 'delete-disposable']);
        $batch = RevisionBatch::query()->where('correlation_id', 'delete-disposable')->firstOrFail();
        $item = RevisionBatchItem::query()->where('revision_batch_id', $batch->getKey())->firstOrFail();
        $deleteSnapshot = $leaf->latestVersions()->firstOrFail();

        $this->assertGreaterThan($priorVersionId, $deleteSnapshot->getKey());
        $this->assertTrue($snapshotCreatedAfterSoftDelete);
        $this->assertSame($deleteSnapshot->getKey(), $item->version_id);
        $this->assertSame('Disposable leaf', $deleteSnapshot->contents['name']);
        $this->assertSame($deleteSnapshot->getKey(), RevisionBatch::query()
            ->whereKey($batch->getKey())
            ->firstOrFail()
            ->items
            ->firstOrFail()
            ->version_id);
        $this->assertSame($deleteSnapshot->getKey(), AuditEvent::query()
            ->where('event_type', 'cost-center.deleted')
            ->where('correlation_id', 'delete-disposable')
            ->firstOrFail()
            ->properties['delete_snapshot_version_id']);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'cost-center.deleted',
            'correlation_id' => 'delete-disposable',
        ]);
    }

    public function test_create_cost_center_audit_failure_rolls_back_the_new_record_snapshot_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create');
        $correlationId = '00000000-0000-4000-8000-000000000101';
        $auditCount = AuditEvent::query()->count();
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $recordCount = CostCenter::withTrashed()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.created') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreateCostCenter::class);
            $action->execute($actor, $context, 'Rollback create', null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('cost_centers', ['tenant_id' => $tenant->getKey(), 'name' => 'Rollback create']);
            $this->assertSame($recordCount, CostCenter::withTrashed()->count());
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_deactivate_cost_center_audit_failure_restores_active_state_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.deactivate');
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Rollback deactivate', 'active' => true, 'lock_version' => 4]);
        $correlationId = '00000000-0000-4000-8000-000000000102';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.deactivated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeactivateCostCenter::class);
            $action->execute($actor, $context, $costCenter, 4, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'active' => true, 'lock_version' => 4]);
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_reactivate_cost_center_audit_failure_restores_inactive_state_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.reactivate');
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Rollback reactivate', 'active' => false, 'lock_version' => 5]);
        $correlationId = '00000000-0000-4000-8000-000000000103';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.reactivated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ReactivateCostCenter::class);
            $action->execute($actor, $context, $costCenter, 5, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'active' => false, 'lock_version' => 5]);
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_delete_cost_center_audit_failure_restores_live_record_and_delete_snapshot_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.delete');
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Rollback delete', 'lock_version' => 6]);
        $correlationId = '00000000-0000-4000-8000-000000000104';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.deleted') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeleteCostCenter::class);
            $action->execute($actor, $context, $costCenter, 6, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'deleted_at' => null]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    /** @return array{Tenant, User, TenantContext} */
    private function context(string ...$abilities): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->for($tenant)->create();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId((int) $tenant->getKey());
        foreach ($abilities as $ability) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Cost center lifecycle '.$ability,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($ability);
            $actor->assignRole($role);
        }
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    /** @return array{Dispatcher, string, array<int, mixed>} */
    private function auditCreatingListeners(): array
    {
        $dispatcher = AuditEvent::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $dispatcher);
        $eventName = 'eloquent.creating: '.AuditEvent::class;

        return [$dispatcher, $eventName, $dispatcher->getRawListeners()[$eventName] ?? []];
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
