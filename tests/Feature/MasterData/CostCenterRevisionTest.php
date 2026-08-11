<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateCostCenter;
use App\Domain\MasterData\Actions\RestoreCostCenterRevision;
use App\Domain\MasterData\Actions\UpdateCostCenter;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Version;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CostCenterRevisionTest extends TestCase
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

    public function test_update_and_restore_create_audited_revision_batches_and_new_current_snapshots(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update', 'cost-center.restore-revision');
        $costCenter = app(CreateCostCenter::class)->execute($actor, $context, 'Original name', null, 'create');
        $original = RevisionBatch::query()->where('correlation_id', 'create')->firstOrFail();
        $originalVersionId = $original->items()->value('version_id');
        $updated = app(UpdateCostCenter::class)->execute($actor, $context, $costCenter, 'Corrected name', null, 1, 'update');

        $this->assertSame('Corrected name', $updated->name);
        $this->assertSame(2, $updated->lock_version);
        $this->assertDatabaseHas('revision_batches', [
            'tenant_id' => $tenant->getKey(),
            'root_subject_id' => $costCenter->getKey(),
            'operation' => 'update',
            'correlation_id' => 'update',
        ]);

        $restored = app(RestoreCostCenterRevision::class)->execute(
            $actor,
            $context,
            $updated,
            $original,
            2,
            'restore',
        );

        $this->assertSame('Original name', $restored->name);
        $this->assertSame(3, $restored->lock_version);
        $this->assertGreaterThan($originalVersionId, $restored->latestVersions()->firstOrFail()->getKey());
        $this->assertDatabaseHas('revision_batches', [
            'tenant_id' => $tenant->getKey(),
            'root_subject_id' => $costCenter->getKey(),
            'operation' => 'restore',
            'restored_from_batch_id' => $original->getKey(),
            'restored_from_version_id' => null,
            'correlation_id' => 'restore',
        ]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'cost-center.restored', 'correlation_id' => 'restore']);
    }

    public function test_restore_revalidates_tenant_identity_and_stale_lock_without_partial_mutation(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update', 'cost-center.restore-revision');
        $costCenter = app(CreateCostCenter::class)->execute($actor, $context, 'Local', null, 'create-local');
        app(UpdateCostCenter::class)->execute($actor, $context, $costCenter, 'Local current', null, 1, 'update-local');
        app(CreateCostCenter::class)->execute($actor, $context, 'Other local center', null, 'other-local');
        $foreignVersion = RevisionBatch::query()->where('correlation_id', 'other-local')->firstOrFail();

        $relationshipFailure = null;
        try {
            app(RestoreCostCenterRevision::class)->execute($actor, $context, $costCenter, $foreignVersion, 2, 'foreign-restore');
            $this->fail('A foreign revision was restored.');
        } catch (ModelNotFoundException $exception) {
            $relationshipFailure = $exception;
        }
        $this->assertInstanceOf(ModelNotFoundException::class, $relationshipFailure);

        $version = RevisionBatch::query()->where('correlation_id', 'create-local')->firstOrFail();
        try {
            app(RestoreCostCenterRevision::class)->execute($actor, $context, $costCenter, $version, 1, 'stale-restore');
            $this->fail('A stale restoration overwrote the current record.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }
        $this->assertSame('Local current', $costCenter->refresh()->name);
        $this->assertSame(2, $costCenter->lock_version);
    }

    public function test_restore_reloads_the_persisted_version_and_rejects_primary_key_or_contents_tampering_without_side_effects(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update', 'cost-center.restore-revision');
        $costCenter = app(CreateCostCenter::class)->execute($actor, $context, 'Original', null, 'restore-original');
        app(UpdateCostCenter::class)->execute($actor, $context, $costCenter, 'Current', null, 1, 'restore-current');
        $version = RevisionBatch::query()->where('correlation_id', 'restore-original')->firstOrFail();
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        $spoofed = clone $version;
        $spoofed->forceFill([$spoofed->getKeyName() => (int) $version->getKey() + 100000]);
        $spoofFailure = null;
        try {
            app(RestoreCostCenterRevision::class)->execute($actor, $context, $costCenter, $spoofed, 2, 'restore-spoofed');
            $this->fail('A primary-key-spoofed Version was accepted.');
        } catch (ModelNotFoundException $exception) {
            $spoofFailure = $exception;
        }
        $this->assertInstanceOf(ModelNotFoundException::class, $spoofFailure);

        $tampered = clone $version;
        $tampered->setRelation('items', collect());
        $restored = app(RestoreCostCenterRevision::class)->execute($actor, $context, $costCenter, $tampered, 2, 'restore-reloaded');
        $this->assertSame('Original', $restored->name);
        $this->assertSame(3, $restored->lock_version);
        $this->assertDatabaseCount('revision_batches', $batchCount + 1);
        $this->assertDatabaseCount('audit_events', $auditCount + 2);
        $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'name' => 'Original', 'lock_version' => 3]);
    }

    public function test_update_cost_center_audit_failure_rolls_back_name_parent_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.update');
        $costCenter = CostCenter::factory()->for($tenant)->create(['name' => 'Rollback update', 'lock_version' => 7]);
        $correlationId = '00000000-0000-4000-8000-000000000105';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.updated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateCostCenter::class);
            $action->execute($actor, $context, $costCenter, 'Changed update', null, 7, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'name' => 'Rollback update', 'parent_id' => null, 'lock_version' => 7]);
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_restore_cost_center_revision_audit_failure_restores_current_snapshot_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.restore-revision');
        $costCenter = app(CreateCostCenter::class)->execute($actor, $context, 'Rollback restore', null, 'rollback-restore-source');
        $version = RevisionBatch::query()->where('correlation_id', 'rollback-restore-source')->firstOrFail();
        $costCenter->forceFill(['name' => 'Current restore', 'lock_version' => 2])->save();
        $correlationId = '00000000-0000-4000-8000-000000000106';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'cost-center.restored') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(RestoreCostCenterRevision::class);
            $action->execute($actor, $context, $costCenter, $version, 2, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('cost_centers', ['id' => $costCenter->getKey(), 'name' => 'Current restore', 'lock_version' => 2]);
            $this->assertDatabaseCount('versions', $versionCount);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('audit_events', $auditCount);
        }
    }

    public function test_restore_parent_change_rejects_a_resulting_fourth_level_from_existing_descendants_atomically(): void
    {
        [$tenant, $actor, $context] = $this->context('cost-center.create', 'cost-center.update', 'cost-center.restore-revision');
        $create = app(CreateCostCenter::class);
        $destinationRoot = $create->execute($actor, $context, 'Restore destination', null, 'restore-depth-destination');
        $moving = $create->execute($actor, $context, 'Restore moving', $destinationRoot, 'restore-depth-moving');
        $parentedVersion = RevisionBatch::query()->where('correlation_id', 'restore-depth-moving')->firstOrFail();
        $moving = app(UpdateCostCenter::class)->execute($actor, $context, $moving, 'Restore moving', null, 1, 'restore-depth-detach');
        $child = $create->execute($actor, $context, 'Restore child', $moving, 'restore-depth-child');
        $create->execute($actor, $context, 'Restore grandchild', $child, 'restore-depth-grandchild');
        $versionCount = Version::query()->count();
        $batchCount = RevisionBatch::query()->count();
        $auditCount = AuditEvent::query()->count();

        try {
            app(RestoreCostCenterRevision::class)->execute(
                $actor,
                $context,
                $moving,
                $parentedVersion,
                2,
                'restore-depth-rejected',
            );
            $this->fail('A restore producing level four through existing descendants was accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('COST_CENTER_DEPTH_EXCEEDED', $exception->getMessage());
        }

        $this->assertDatabaseHas('cost_centers', [
            'id' => $moving->getKey(),
            'parent_id' => null,
            'name' => 'Restore moving',
            'lock_version' => 2,
        ]);
        $this->assertDatabaseHas('revision_batches', ['id' => $parentedVersion->getKey()]);
        $this->assertDatabaseCount('versions', $versionCount);
        $this->assertDatabaseCount('revision_batches', $batchCount);
        $this->assertDatabaseCount('audit_events', $auditCount);
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
                'name' => 'Cost center revision '.$ability,
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
