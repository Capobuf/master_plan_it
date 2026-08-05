<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateVendor;
use App\Domain\MasterData\Actions\DeactivateVendor;
use App\Domain\MasterData\Actions\ReactivateVendor;
use App\Domain\MasterData\Queries\VendorSelectorQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\RevisionBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendorLifecycleTest extends TestCase
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

    public function test_deactivation_and_reactivation_are_optimistic_audited_and_update_selector_eligibility(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.deactivate', 'vendor.reactivate', 'vendor.view');
        $vendor = app(CreateVendor::class)->execute($actor, $context, 'Lifecycle vendor', null, null, null, null, 'create');

        $deactivated = app(DeactivateVendor::class)->execute($actor, $context, $vendor, 1, 'deactivate');
        $this->assertFalse($deactivated->active);
        $this->assertSame(2, $deactivated->lock_version);
        $this->assertSame([], app(VendorSelectorQuery::class)->forNewSelection($actor, $context)->pluck('id')->all());
        $this->assertSame([$vendor->getKey()], app(VendorSelectorQuery::class)
            ->forRecord($actor, $context, $vendor->getKey())
            ->pluck('id')
            ->all());

        try {
            app(ReactivateVendor::class)->execute($actor, $context, $deactivated, 1, 'stale-reactivate');
            $this->fail('A stale lifecycle request overwrote the vendor.');
        } catch (DomainException $exception) {
            $this->assertSame('STALE_VERSION', $exception->getMessage());
        }

        $reactivated = app(ReactivateVendor::class)->execute($actor, $context, $deactivated, 2, 'reactivate');
        $this->assertTrue($reactivated->active);
        $this->assertSame(3, $reactivated->lock_version);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'vendor.deactivated', 'correlation_id' => 'deactivate']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'vendor.reactivated', 'correlation_id' => 'reactivate']);
        $this->assertSame($tenant->getKey(), $reactivated->tenant_id);
    }

    public function test_deactivate_vendor_audit_failure_restores_active_state_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.deactivate');
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Rollback deactivate', 'active' => true, 'lock_version' => 4]);
        $correlationId = '00000000-0000-4000-8000-000000000203';
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.deactivated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeactivateVendor::class);
            $action->execute($actor, $context, $vendor, 4, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'active' => true, 'lock_version' => 4]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
        }
    }

    public function test_reactivate_vendor_audit_failure_restores_inactive_state_lock_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.reactivate');
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Rollback reactivate', 'active' => false, 'lock_version' => 5]);
        $correlationId = '00000000-0000-4000-8000-000000000204';
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.reactivated') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(ReactivateVendor::class);
            $action->execute($actor, $context, $vendor, 5, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'active' => false, 'lock_version' => 5]);
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
                'name' => 'Vendor lifecycle '.$ability,
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
