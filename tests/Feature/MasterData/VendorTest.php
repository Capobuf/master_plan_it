<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Actions\CreateVendor;
use App\Domain\MasterData\Actions\DeleteVendor;
use App\Domain\MasterData\Actions\UpdateVendor;
use App\Domain\MasterData\Queries\VendorListQuery;
use App\Domain\MasterData\Queries\VendorSelectorQuery;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\RevisionBatch;
use App\Models\RevisionBatchItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Version;
use Database\Seeders\PermissionCatalogueSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendorTest extends TestCase
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

    public function test_vendor_writes_are_authorized_tenant_scoped_and_validate_name_and_optional_contact_fields(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.update', 'vendor.view');
        $create = app(CreateVendor::class);
        $vendor = $create->execute($actor, $context, 'Acme S.r.l.', 'IT01234567890', 'billing@acme.test', '+39 02 1234', 'Via Roma 1', 'create');

        $this->assertSame($tenant->getKey(), $vendor->tenant_id);
        $this->assertSame('IT01234567890', $vendor->vat_number);
        $this->assertSame('billing@acme.test', $vendor->email);
        $this->assertSame(1, $vendor->lock_version);

        try {
            $create->execute($actor, $context, 'Acme S.r.l.', null, null, null, null, 'duplicate');
            $this->fail('A tenant-scoped duplicate vendor name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $minimal = $create->execute($actor, $context, 'No contact supplier', null, null, null, null, 'minimal');
        $this->assertNull($minimal->vat_number);
        $this->assertNull($minimal->email);

        try {
            app(UpdateVendor::class)->execute($actor, $context, $vendor, 'Acme S.r.l.', 'VAT', 'not-an-email', null, null, 1, 'invalid');
            $this->fail('An invalid vendor e-mail was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $foreign = Vendor::factory()->for(Tenant::factory())->create();
        try {
            app(UpdateVendor::class)->execute($actor, $context, $foreign, 'Tampered', null, null, null, null, 1, 'foreign');
            $this->fail('A foreign vendor was mutated.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame([$vendor->getKey(), $minimal->getKey()], app(VendorListQuery::class)
            ->forTenant($actor, $context)
            ->pluck('id')
            ->all());
    }

    public function test_vendor_delete_is_constrained_by_current_and_historical_references_and_preserves_audit_evidence(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.delete');
        $create = app(CreateVendor::class);
        $current = $create->execute($actor, $context, 'Current reference', null, null, null, null, 'create-current');
        $historical = $create->execute($actor, $context, 'Historical reference', null, null, null, null, 'create-historical');

        $expenseId = DB::table('expenses')->insertGetId([
            'tenant_id' => $tenant->getKey(),
            'planning_year_id' => PlanningYear::factory()->for($tenant)->create()->getKey(),
            'cost_center_id' => CostCenter::factory()->for($tenant)->create()->getKey(),
            'kind' => 'ordinary',
            'title' => 'Vendor reference',
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $currentRowId = $this->insertExpenseRow($tenant->getKey(), $expenseId, $current->getKey(), 1);
        $historicalRowId = $this->insertExpenseRow($tenant->getKey(), $expenseId, $historical->getKey(), 2);
        $this->assertDatabaseHas('expense_rows', [
            'id' => $currentRowId,
            'tenant_id' => $tenant->getKey(),
            'vendor_id' => $current->getKey(),
        ]);
        DB::table('expense_rows')->where('id', $historicalRowId)->update(['deleted_at' => now()]);

        foreach ([[$current, 'CURRENT'], [$historical, 'HISTORICAL']] as [$vendor, $kind]) {
            try {
                app(DeleteVendor::class)->execute($actor, $context, $vendor, 1, 'delete-'.$kind);
                $this->fail("A {$kind} referenced vendor was deleted.");
            } catch (DomainException $exception) {
                $this->assertSame('REFERENCED_RECORD_DELETE_DENIED', $exception->getMessage());
            }
        }

        $disposable = $create->execute($actor, $context, 'Disposable vendor', null, null, null, null, 'create-disposable');
        $priorVersionId = $disposable->latestVersions()->firstOrFail()->getKey();
        $snapshotCreatedAfterSoftDelete = false;
        $versionDispatcher = Version::getEventDispatcher();
        $this->assertInstanceOf(Dispatcher::class, $versionDispatcher);
        $versionEvent = 'eloquent.creating: '.Version::class;
        $versionListeners = $versionDispatcher->getRawListeners()[$versionEvent] ?? [];
        Version::creating(function (Version $version) use ($disposable, &$snapshotCreatedAfterSoftDelete): void {
            if ((string) $version->getAttribute('versionable_type') !== $disposable->getMorphClass()
                || (int) $version->getAttribute('versionable_id') !== (int) $disposable->getKey()) {
                return;
            }

            $snapshotCreatedAfterSoftDelete = Vendor::query()->find($disposable->getKey()) === null
                && Vendor::withTrashed()->find($disposable->getKey()) !== null;
        });
        try {
            app(DeleteVendor::class)->execute($actor, $context, $disposable, 1, 'delete-disposable');
        } finally {
            $versionDispatcher->forget($versionEvent);
            foreach ($versionListeners as $listener) {
                $versionDispatcher->listen($versionEvent, $listener);
            }
        }

        $this->assertNull(Vendor::query()->find($disposable->getKey()));
        $this->assertNotNull(Vendor::withTrashed()->find($disposable->getKey()));
        $batch = RevisionBatch::query()->where('correlation_id', 'delete-disposable')->firstOrFail();
        $item = RevisionBatchItem::query()->where('revision_batch_id', $batch->getKey())->firstOrFail();
        $deleteSnapshot = $disposable->latestVersions()->firstOrFail();
        $audit = AuditEvent::query()->where('event_type', 'vendor.deleted')->where('correlation_id', 'delete-disposable')->firstOrFail();
        $this->assertGreaterThan($priorVersionId, $deleteSnapshot->getKey());
        $this->assertTrue($snapshotCreatedAfterSoftDelete);
        $this->assertSame($deleteSnapshot->getKey(), $item->version_id);
        $this->assertSame($deleteSnapshot->getKey(), $audit->properties['delete_snapshot_version_id']);
    }

    public function test_actions_list_and_selectors_reject_inactive_or_forged_context_and_bound_current_inactive_value(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create', 'vendor.view');
        $active = Vendor::factory()->for($tenant)->create(['name' => 'Active', 'active' => true]);
        $inactive = Vendor::factory()->for($tenant)->create(['name' => 'Inactive current', 'active' => false]);
        $otherInactive = Vendor::factory()->for($tenant)->create(['name' => 'Inactive other', 'active' => false]);
        $foreignInactive = Vendor::factory()->for(Tenant::factory())->create(['name' => 'Foreign inactive', 'active' => false]);
        $selector = app(VendorSelectorQuery::class);

        $this->assertSame([$active->getKey()], $selector->forNewSelection($actor, $context)->pluck('id')->all());
        $recordOptions = $selector->forRecord($actor, $context, $inactive->getKey())->pluck('id')->all();
        $this->assertContains($active->getKey(), $recordOptions);
        $this->assertContains($inactive->getKey(), $recordOptions);
        $this->assertNotContains($otherInactive->getKey(), $recordOptions);
        $this->assertNotContains($foreignInactive->getKey(), $recordOptions);

        Tenant::query()->whereKey($tenant->getKey())->update(['state' => TenantState::Inactive->value]);
        $this->assertAuthorizationCode(fn (): mixed => app(VendorListQuery::class)->forTenant($actor, $context)->get(), 'TENANT_INACTIVE');
        $this->assertAuthorizationCode(fn (): mixed => $selector->forNewSelection($actor, $context), 'TENANT_INACTIVE');
        $this->assertAuthorizationCode(
            fn (): Vendor => app(CreateVendor::class)->execute($actor, $context, 'Inactive write', null, null, null, null, 'inactive-write'),
            'TENANT_INACTIVE',
        );

        $forged = $tenant->replicate();
        $forged->forceFill(['id' => $tenant->getKey() + 100000]);
        $forged->exists = true;
        $forgedContext = new TenantContext($forged, $actor);
        $this->assertAuthorizationCode(fn (): mixed => app(VendorListQuery::class)->forTenant($actor, $forgedContext)->get(), 'TENANT_CONTEXT_REQUIRED');
        $this->assertAuthorizationCode(fn (): mixed => $selector->forNewSelection($actor, $forgedContext), 'TENANT_CONTEXT_REQUIRED');
        $this->assertAuthorizationCode(
            fn (): Vendor => app(CreateVendor::class)->execute($actor, $forgedContext, 'Forged write', null, null, null, null, 'forged-write'),
            'TENANT_CONTEXT_REQUIRED',
        );
    }

    public function test_create_vendor_audit_failure_rolls_back_record_snapshot_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.create');
        $correlationId = '00000000-0000-4000-8000-000000000201';
        $batchCount = RevisionBatch::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.created') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(CreateVendor::class);
            $action->execute($actor, $context, 'Rollback create vendor', null, null, null, null, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseMissing('vendors', ['tenant_id' => $tenant->getKey(), 'name' => 'Rollback create vendor']);
            $this->assertDatabaseCount('revision_batches', $batchCount);
        }
    }

    public function test_delete_vendor_audit_failure_restores_live_record_snapshot_and_batch(): void
    {
        [$tenant, $actor, $context] = $this->context('vendor.delete');
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Rollback delete vendor', 'lock_version' => 6]);
        $correlationId = '00000000-0000-4000-8000-000000000202';
        $batchCount = RevisionBatch::query()->count();
        $versionCount = Version::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();
        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId && $event->event_type === 'vendor.deleted') {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(DeleteVendor::class);
            $action->execute($actor, $context, $vendor, 6, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('vendors', ['id' => $vendor->getKey(), 'deleted_at' => null, 'lock_version' => 6]);
            $this->assertDatabaseCount('revision_batches', $batchCount);
            $this->assertDatabaseCount('versions', $versionCount);
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
                'name' => 'Vendor '.$ability,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($ability);
            $actor->assignRole($role);
        }
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');

        return [$tenant, $actor, new TenantContext($tenant, $actor)];
    }

    private function insertExpenseRow(int $tenantId, int $expenseId, int $vendorId, int $position): int
    {
        return DB::table('expense_rows')->insertGetId([
            'tenant_id' => $tenantId,
            'expense_id' => $expenseId,
            'position' => $position,
            'vendor_id' => $vendorId,
            'type' => 'estimate',
            'description' => 'Vendor reference',
            'entered_amount' => '100.000000',
            'amount_includes_vat' => false,
            'vat_rate' => '22.000000',
            'net_amount' => '100.00',
            'vat_amount' => '22.00',
            'gross_amount' => '122.00',
            'is_extra' => false,
            'spend_date' => '2026-01-15',
            'lock_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertAuthorizationCode(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail("Expected authorization code [{$code}].");
        } catch (AuthorizationException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
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
