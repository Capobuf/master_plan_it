<?php

namespace Tests\Feature\PlatformOperations;

use App\Domain\Budget\Actions\ApplyBudgetApproval;
use App\Domain\Budget\Data\ApplyApprovalData;
use App\Domain\Budget\Data\ApprovalChangeData;
use App\Domain\Expenses\Enums\ExpenseType;
use App\Domain\Tenancy\Actions\UpdateTenantSettings;
use App\Domain\Tenancy\Data\TenantContext;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\Expense;
use App\Models\ExpenseRow;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Authorization\PlatformAdministrator;
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

    protected function tearDown(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_view_and_update_are_independent_and_update_implies_view(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Tenant A',
            'default_vat_rate' => '22.00',
            'lock_version' => 7,
        ]);
        $viewer = $this->tenantActor($tenant, ['tenant-settings.view']);
        $this->actingAs($viewer, 'web');

        $this->getJson('/api/v1/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->getKey())
            ->assertJsonPath('data.name', 'Tenant A');
        $this->withHeaders($this->csrfHeaders())
            ->putJson('/api/v1/tenant-settings', $this->settingsPayload($tenant, ['name' => 'Denied']))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Tenant A',
            'lock_version' => 7,
        ]);

        $role = Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'like', 'Tenant settings %')
            ->firstOrFail();
        $role->givePermissionTo('tenant-settings.update');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer->unsetRelation('roles');
        $viewer->unsetRelation('permissions');
        $this->actingAs($viewer, 'web');
        $this->getJson('/api/v1/tenant-settings')->assertOk();
        $this->withHeaders($this->csrfHeaders())
            ->putJson('/api/v1/tenant-settings', $this->settingsPayload($tenant, [
                'name' => 'Tenant A aggiornato',
                'default_vat_rate' => '20.00',
                'deletion_reason_required' => true,
            ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Tenant A aggiornato')
            ->assertJsonPath('data.default_vat_rate', '20.00')
            ->assertJsonPath('data.deletion_reason_required', true)
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonMissingPath('data.attachment_quota_bytes')
            ->assertJsonMissingPath('data.code')
            ->assertJsonMissingPath('data.language_code');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'actor_user_id' => $viewer->getKey(),
            'event_type' => 'tenant.settings.updated',
        ]);
    }

    public function test_payload_is_closed_and_invalid_settings_do_not_mutate_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Closed payload',
            'default_vat_rate' => '22.00',
            'lock_version' => 2,
        ]);
        $manager = $this->tenantActor($tenant, ['tenant-settings.update']);
        $this->actingAs($manager, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'tenant_id' => Tenant::factory()->create()->getKey(),
            'attachment_quota_bytes' => '0',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

        foreach (['-0.01', '22.123', '10000000000.00'] as $invalidVat) {
            $this->withHeaders($headers)->putJson('/api/v1/tenant-settings', [
                ...$this->settingsPayload($tenant),
                'default_vat_rate' => $invalidVat,
            ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Closed payload',
            'default_vat_rate' => '22.00',
            'lock_version' => 2,
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'tenant_id' => $tenant->getKey(),
            'event_type' => 'tenant.settings.updated',
        ]);
    }

    public function test_projection_is_exact_and_audit_contains_only_changed_field_names(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Exact projection',
            'currency_code' => 'EUR',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
            'budget_basis' => 'net',
            'deletion_reason_required' => false,
            'lock_version' => 1,
        ]);
        $manager = $this->tenantActor($tenant, ['tenant-settings.update']);
        $this->actingAs($manager, 'web');

        $this->getJson('/api/v1/tenant-settings')->assertExactJson([
            'data' => [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Exact projection',
                'currency_code' => 'EUR',
                'timezone' => 'Europe/Rome',
                'default_vat_rate' => '22.00',
                'economic_basis' => 'net',
                'economic_basis_locked_at' => null,
                'deletion_reason_required' => false,
                'lock_version' => 1,
            ],
        ]);

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'name' => 'Exact projection aggiornata',
        ])->assertOk();

        $event = AuditEvent::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('event_type', 'tenant.settings.updated')
            ->sole();
        $this->assertSame(['changed_fields' => ['name']], $event->properties);
        $this->assertArrayNotHasKey('name', $event->properties);
        $this->assertArrayNotHasKey('default_vat_rate', $event->properties);
    }

    public function test_economic_basis_projection_locks_after_approval_and_mutation_is_atomic(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Approved tenant',
            'budget_basis' => 'net',
            'lock_version' => 1,
        ]);
        $administrator = User::factory()->create(['tenant_id' => null, 'is_active' => true]);
        app(PermissionCatalogueSeeder::class)->run();
        app(PlatformAdministrator::class)->assign($administrator);
        $year = PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $expense = Expense::factory()->for($tenant)->create([
            'planning_year_id' => $year->getKey(),
        ]);
        ExpenseRow::factory()->for($expense)->create([
            'tenant_id' => $tenant->getKey(),
            'type' => ExpenseType::Estimate,
            'spend_date' => null,
        ]);
        app(ApplyBudgetApproval::class)->execute(
            $administrator,
            new TenantContext($tenant, $administrator),
            $year,
            new ApplyApprovalData(1, '2026-02-01', null, [
                new ApprovalChangeData((int) $expense->getKey(), 1, '100.00'),
            ]),
            (string) str()->uuid(),
        );

        $manager = $this->tenantActor($tenant, ['tenant-settings.update']);
        $this->actingAs($manager, 'web');
        $this->getJson('/api/v1/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.economic_basis', 'net');
        $this->assertNotNull($this->getJson('/api/v1/tenant-settings')->json('data.economic_basis_locked_at'));
        $settingsAuditCount = AuditEvent::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('event_type', 'tenant.settings.updated')
            ->count();

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenant-settings', [
            ...$this->settingsPayload($tenant),
            'economic_basis' => 'gross',
        ])->assertConflict()->assertJsonPath('error.code', 'BUDGET_STATE_CONFLICT');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'budget_basis' => 'net',
            'lock_version' => 1,
        ]);
        $this->assertSame(
            $settingsAuditCount,
            AuditEvent::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('event_type', 'tenant.settings.updated')
                ->count(),
        );
    }

    public function test_stale_update_has_no_tenant_or_audit_side_effect(): void
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

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Stable tenant',
            'lock_version' => 4,
        ]);
        $this->assertDatabaseCount('audit_events', $auditCount);
    }

    public function test_inactive_actor_and_tenant_are_denied_without_data_or_mutation(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Inactive boundary', 'lock_version' => 1]);
        $actor = $this->tenantActor($tenant, ['tenant-settings.update']);
        $this->actingAs($actor, 'web');

        $actor->forceFill(['is_active' => false])->save();
        $this->getJson('/api/v1/tenant-settings')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');

        $actor->forceFill(['is_active' => true])->save();
        $tenant->forceFill(['state' => TenantState::Inactive])->save();
        $this->actingAs($actor->refresh(), 'web');
        $this->getJson('/api/v1/tenant-settings')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TENANT_INACTIVE');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->getKey(),
            'name' => 'Inactive boundary',
            'lock_version' => 1,
        ]);
    }

    public function test_administrator_reads_selected_tenant_without_membership(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create(['name' => 'Selected tenant']);
        $this->actingAs($administrator, 'web');
        $headers = $this->csrfHeaders();

        $this->withHeaders($headers)
            ->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')
            ->assertOk();
        $this->getJson('/api/v1/tenant-settings')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->getKey())
            ->assertJsonPath('data.name', 'Selected tenant');

        $this->assertNull($administrator->tenant_id);
    }

    public function test_audit_failure_rolls_back_settings_and_audit(): void
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
            $action->execute(
                $manager,
                $context,
                [
                    'name' => 'Unsafe update',
                    'timezone' => 'Europe/Rome',
                    'default_vat_rate' => '20.00',
                    'economic_basis' => 'net',
                    'deletion_reason_required' => true,
                ],
                5,
                $correlationId,
            );
        } finally {
            $this->restoreAuditCreatingListeners($dispatcher, $eventName, $listeners);
            $this->assertDatabaseHas('tenants', [
                'id' => $tenant->getKey(),
                'name' => 'Before rollback',
                'lock_version' => 5,
            ]);
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
            'name' => 'Tenant settings '.str()->uuid(),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($abilities);
        $actor = User::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'is_active' => true,
        ]);
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
            'economic_basis' => (string) $tenant->getRawOriginal('budget_basis'),
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
