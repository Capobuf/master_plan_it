<?php

namespace Tests\Feature\PlatformOperations;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Domain\Platform\Actions\UpdateAuditRetention;
use App\Domain\Tenancy\Actions\UpdateTenantSettings;
use App\Domain\Tenancy\Data\TenantContext;
use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionCatalogueSeeder;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class TenantSettingsTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_settings_are_tenant_scoped_with_independent_view_and_update_abilities(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Tenant A', 'default_vat_rate' => '22.00', 'lock_version' => 7]);
        $foreign = Tenant::factory()->create(['name' => 'Tenant B', 'default_vat_rate' => '10.00']);
        $viewer = $this->tenantActor($tenant, ['tenant-settings.view']);
        $this->actingAs($viewer, 'web');

        $this->getJson('/api/v1/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->getKey())
            ->assertJsonPath('data.name', 'Tenant A')
            ->assertJsonMissing(['Tenant B']);

        $payload = $this->settingsPayload($tenant, ['name' => 'Not allowed']);
        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', $payload)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'name' => 'Tenant A', 'lock_version' => 7]);
        $this->assertDatabaseHas('tenants', ['id' => $foreign->getKey(), 'name' => 'Tenant B', 'default_vat_rate' => '10.00']);

        $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'like', 'Feature 008 %')->firstOrFail();
        $role->givePermissionTo('tenant-settings.update');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer->unsetRelation('roles');
        $this->actingAs($viewer, 'web');
        $updated = $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'name' => 'Tenant A aggiornato',
            'default_vat_rate' => '20.00',
            'deletion_reason_required' => true,
        ])->assertOk();

        $updated->assertJsonPath('data.name', 'Tenant A aggiornato')
            ->assertJsonPath('data.default_vat_rate', '20.00')
            ->assertJsonPath('data.deletion_reason_required', true)
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonMissingPath('data.attachment_quota_bytes');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant->refresh()),
            'tenant_id' => $foreign->getKey(),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseHas('tenants', ['id' => $foreign->getKey(), 'name' => 'Tenant B', 'default_vat_rate' => '10.00']);
    }

    public function test_stale_settings_update_has_no_side_effect(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Stable tenant', 'lock_version' => 4]);
        $manager = $this->tenantActor($tenant, ['tenant-settings.update']);
        $this->actingAs($manager, 'web');
        $auditCount = AuditEvent::query()->count();

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'name' => 'Stale mutation',
            'lock_version' => 3,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'name' => 'Stable tenant', 'lock_version' => 4]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_deletion_reason_setting_changes_only_future_supported_deletions(): void
    {
        $tenant = Tenant::factory()->create(['deletion_reason_required' => false, 'lock_version' => 1]);
        $manager = $this->tenantActor($tenant, ['tenant-settings.update', 'project.delete']);
        $before = Project::factory()->for($tenant)->create(['title' => 'Delete before setting']);
        $after = Project::factory()->for($tenant)->create(['title' => 'Delete after setting']);
        $this->actingAs($manager, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)->deleteJson('/api/v1/projects/'.$before->getKey(), [
            'lock_version' => 1,
        ])->assertNoContent();
        $this->assertDatabaseHas('projects', ['id' => $before->getKey(), 'deletion_reason' => null]);

        $this->withHeaders($headers)->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'deletion_reason_required' => true,
        ])->assertOk()->assertJsonPath('data.deletion_reason_required', true);

        $this->assertDatabaseHas('projects', ['id' => $before->getKey(), 'deletion_reason' => null]);
        $this->withHeaders($headers)->deleteJson('/api/v1/projects/'.$after->getKey(), [
            'lock_version' => 1,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertDatabaseHas('projects', ['id' => $after->getKey(), 'deleted_at' => null]);

        $this->withHeaders($headers)->deleteJson('/api/v1/projects/'.$after->getKey(), [
            'lock_version' => 1,
            'deletion_reason' => 'Fine attività',
        ])->assertNoContent();
        $this->assertDatabaseHas('projects', ['id' => $after->getKey(), 'deletion_reason' => 'Fine attività']);
    }

    public function test_update_tenant_settings_audit_failure_rolls_back_tenant_and_audit(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Before rollback', 'lock_version' => 5]);
        $manager = $this->tenantActor($tenant, ['tenant-settings.update']);
        $context = new TenantContext($tenant, $manager);
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateTenantSettings::class);
            $action->execute($manager, $context, [
                'name' => 'Unsafe update',
                'timezone' => 'Europe/Rome',
                'default_vat_rate' => '20.00',
                'budget_basis' => 'net',
                'deletion_reason_required' => true,
            ], 5, $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'name' => 'Before rollback', 'lock_version' => 5]);
            $this->assertDatabaseMissing('tenants', ['id' => $tenant->getKey(), 'name' => 'Unsafe update']);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    public function test_update_audit_retention_failure_rolls_back_setting_pruning_and_audit(): void
    {
        $administrator = $this->administrator();
        PlatformSetting::query()->create(['id' => 1, 'audit_retention_months' => 24, 'lock_version' => 1]);
        $oldCorrelationId = (string) str()->uuid();
        app(AuditRecorder::class)->record(
            'old.audit.fixture',
            $oldCorrelationId,
            new AuditProperties([]),
            occurredAt: CarbonImmutable::now('UTC')->subMonthsNoOverflow(18),
        );
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        [$dispatcher, $eventName, $listeners] = $this->auditCreatingListeners();

        AuditEvent::creating(function (AuditEvent $event) use ($correlationId): void {
            if ($event->correlation_id === $correlationId) {
                throw new RuntimeException('forced audit failure');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $action = app(UpdateAuditRetention::class);
            $action->execute($administrator, 12, 1, 'RIDUCI AUDIT A 12 MESI', $correlationId);
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('platform_settings', ['id' => 1, 'audit_retention_months' => 24, 'lock_version' => 1]);
            $this->assertDatabaseHas('audit_events', ['correlation_id' => $oldCorrelationId]);
            $this->assertDatabaseCount('audit_events', $auditCount);
            $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
        }
    }

    /** @param list<string> $abilities */
    private function tenantActor(Tenant $tenant, array $abilities): User
    {
        Artisan::call('db:seed', ['--class' => PermissionCatalogueSeeder::class, '--force' => true]);
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Feature 008 '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor = User::factory()->create(['tenant_id' => $tenant->getKey(), 'is_active' => true]);
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId((int) $tenant->getKey());

        try {
            $actor->assignRole($role);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }

        return $actor;
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(Tenant $tenant, array $overrides = []): array
    {
        return [...[
            'name' => (string) $tenant->name,
            'timezone' => (string) $tenant->timezone,
            'default_vat_rate' => (string) $tenant->default_vat_rate,
            'budget_basis' => (string) $tenant->getRawOriginal('budget_basis'),
            'deletion_reason_required' => (bool) $tenant->deletion_reason_required,
            'lock_version' => (int) $tenant->lock_version,
        ], ...$overrides];
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
