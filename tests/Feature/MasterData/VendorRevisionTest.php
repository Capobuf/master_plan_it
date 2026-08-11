<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateVendor;
use App\Domain\MasterData\Actions\RestoreVendorRevision;
use App\Domain\MasterData\Actions\UpdateVendor;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
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

class VendorRevisionTest extends TestCase
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

    public function test_update_and_restore_create_audited_revision_batches_and_revalidate_current_state(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.update', 'vendor.restore-revision');
        $vendor = app(CreateVendor::class)->execute($actor, $context, 'Original supplier', 'IT001', null, null, null, 'create');
        $original = RevisionBatch::query()->where('correlation_id', 'create')->firstOrFail();
        $originalVersionId = $original->items()->value('version_id');
        $updated = app(UpdateVendor::class)->execute($actor, $context, $vendor, 'Updated supplier', 'IT002', 'payables@example.test', null, null, 1, 'update');

        $this->assertSame('Updated supplier', $updated->name);
        $this->assertSame(2, $updated->lock_version);
        $this->assertDatabaseHas('revision_batches', [
            'tenant_id' => $tenant->getKey(),
            'root_subject_id' => $vendor->getKey(),
            'operation' => 'update',
            'correlation_id' => 'update',
        ]);

        $restored = app(RestoreVendorRevision::class)->execute($actor, $context, $updated, $original, 2, 'restore');
        $this->assertSame('Original supplier', $restored->name);
        $this->assertSame(3, $restored->lock_version);
        $this->assertGreaterThan($originalVersionId, $restored->latestVersions()->firstOrFail()->getKey());
        $this->assertDatabaseHas('revision_batches', [
            'tenant_id' => $tenant->getKey(),
            'root_subject_id' => $vendor->getKey(),
            'operation' => 'restore',
            'restored_from_batch_id' => $original->getKey(),
            'restored_from_version_id' => null,
            'correlation_id' => 'restore',
        ]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'vendor.restored', 'correlation_id' => 'restore']);
    }

    public function test_restore_rejects_foreign_revision_and_stale_lock_without_mutating_current_vendor(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.update', 'vendor.restore-revision');
        $vendor = app(CreateVendor::class)->execute($actor, $context, 'Local vendor', null, null, null, null, 'create');
        app(UpdateVendor::class)->execute($actor, $context, $vendor, 'Local current', null, null, null, null, 1, 'update');
        app(CreateVendor::class)->execute($actor, $context, 'Other local vendor', null, null, null, null, 'other-local');
        $foreignVersion = RevisionBatch::query()->where('correlation_id', 'other-local')->firstOrFail();

        $relationshipFailure = null;
        try {
            app(RestoreVendorRevision::class)->execute($actor, $context, $vendor, $foreignVersion, 2, 'foreign');
            $this->fail('A foreign revision was restored.');
        } catch (ModelNotFoundException $exception) {
            $relationshipFailure = $exception;
        }
        $this->assertInstanceOf(ModelNotFoundException::class, $relationshipFailure);

        $version = RevisionBatch::query()->where('correlation_id', 'create')->firstOrFail();
        try {
            app(RestoreVendorRevision::class)->execute($actor, $context, $vendor, $version, 1, 'stale');
            $this->fail('A stale revision restore overwrote the vendor.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }
        $this->assertSame('Local current', $vendor->refresh()->name);
        $this->assertSame(2, $vendor->lock_version);
    }

    public function test_restore_rejects_spoofed_version_identity_and_ignores_in_memory_contents_and_relation_tamper(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.update', 'vendor.restore-revision');
        $vendor = app(CreateVendor::class)->execute($actor, $context, 'Persisted original', 'IT001', null, null, null, 'create');
        $original = RevisionBatch::query()->where('correlation_id', 'create')->firstOrFail();
        $updated = app(UpdateVendor::class)->execute($actor, $context, $vendor, 'Persisted current', 'IT002', null, null, null, 1, 'update');
        $spoofed = clone $original;
        $spoofed->forceFill([$spoofed->getKeyName() => (int) $original->getKey() + 100000]);
        $batchCount = RevisionBatch::query()->count();

        $spoofFailure = null;
        try {
            app(RestoreVendorRevision::class)->execute($actor, $context, $updated, $spoofed, 2, 'spoofed');
            $this->fail('A spoofed revision batch primary key was accepted.');
        } catch (ModelNotFoundException $exception) {
            $spoofFailure = $exception;
        }
        $this->assertInstanceOf(ModelNotFoundException::class, $spoofFailure);
        $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'name' => 'Persisted current', 'lock_version' => 2]);
        $this->assertDatabaseCount('revision_batches', $batchCount);

        $tampered = clone $original;
        $tampered->setRelation('items', collect());
        $restored = app(RestoreVendorRevision::class)->execute($actor, $context, $updated, $tampered, 2, 'tamper-safe-restore');

        $this->assertSame('Persisted original', $restored->name);
        $this->assertSame('IT001', $restored->vat_number);
        $this->assertTrue($restored->active);
        $this->assertSame(3, $restored->lock_version);
    }

    public function test_update_vendor_audit_failure_rolls_back_fields_lock_snapshot_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.update');
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Before update', 'lock_version' => 4]);
        $correlationId = '00000000-0000-4000-8000-000000000205';
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.updated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateVendor::class);
            $action->execute($actor, $context, $vendor, 'After update', null, null, null, null, 4, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'name' => 'Before update', 'lock_version' => 4]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
        }
    }

    public function test_restore_vendor_revision_audit_failure_restores_current_fields_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.update', 'vendor.restore-revision');
        $vendor = app(CreateVendor::class)->execute($actor, $context, 'Restore original', null, null, null, null, 'create-restore-source');
        $original = RevisionBatch::query()->where('correlation_id', 'create-restore-source')->firstOrFail();
        $updated = app(UpdateVendor::class)->execute($actor, $context, $vendor, 'Restore current', null, null, null, null, 1, 'prepare-restore');
        $correlationId = '00000000-0000-4000-8000-000000000206';
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.restored') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(RestoreVendorRevision::class);
            $action->execute($actor, $context, $updated, $original, 2, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'name' => 'Restore current', 'lock_version' => 2]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
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
                'name' => 'Vendor revision '.$ability,
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
