<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Enums\BudgetBasis;
use App\Domain\Tenancy\Enums\TenantState;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_tenant_is_active_and_inactive_is_the_only_other_persistable_state(): void
    {
        $this->assertTenantTableExists();

        DB::table('tenants')->insert($this->requiredTenantAttributes());
        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'code' => 'inactive-tenant',
            'state' => 'inactive',
        ]);

        $this->assertSame('active', DB::table('tenants')->where('code', 'lifecycle-tenant')->value('state'));
        $this->assertSame('inactive', DB::table('tenants')->where('code', 'inactive-tenant')->value('state'));
    }

    public function test_tenant_state_rejects_values_outside_active_and_inactive(): void
    {
        $this->assertTenantTableExists();

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert([
            ...$this->requiredTenantAttributes(),
            'state' => 'archived',
        ]);
    }

    public function test_tenant_has_no_soft_delete_column_and_model_rejects_permanent_deletion(): void
    {
        $this->assertTenantTableExists();
        $this->assertFalse(Schema::hasColumn('tenants', 'deleted_at'));
        $this->assertTrue(class_exists(Tenant::class), 'Tenant model is missing.');

        $tenant = Tenant::query()->create($this->requiredTenantAttributes());

        $this->expectException(\LogicException::class);

        $tenant->delete();
    }

    public function test_tenant_model_rejects_bulk_eloquent_deletion(): void
    {
        $this->assertTenantTableExists();

        $deletedTenant = Tenant::query()->create($this->requiredTenantAttributes());
        $forceDeletedTenant = Tenant::query()->create([
            ...$this->requiredTenantAttributes(),
            'code' => 'force-delete-tenant',
        ]);

        $this->assertTenantDeletionRejected(
            fn () => Tenant::query()->whereKey($deletedTenant)->delete(),
            $deletedTenant,
            'bulk Eloquent deletion',
        );
        $this->assertTenantDeletionRejected(
            fn () => Tenant::query()->whereKey($forceDeletedTenant)->forceDelete(),
            $forceDeletedTenant,
            'bulk Eloquent force deletion',
        );
    }

    public function test_tenant_lock_version_supports_compare_and_swap_updates(): void
    {
        $this->assertTenantTableExists();

        DB::table('tenants')->insert($this->requiredTenantAttributes());

        $currentUpdate = DB::table('tenants')
            ->where('code', 'lifecycle-tenant')
            ->where('lock_version', 1)
            ->update([
                'state' => 'inactive',
                'lock_version' => 2,
                'updated_at' => now(),
            ]);
        $staleUpdate = DB::table('tenants')
            ->where('code', 'lifecycle-tenant')
            ->where('lock_version', 1)
            ->update([
                'state' => 'active',
                'lock_version' => 2,
                'updated_at' => now(),
            ]);

        $this->assertSame(1, $currentUpdate);
        $this->assertSame(0, $staleUpdate);
        $this->assertSame(2, (int) DB::table('tenants')
            ->where('code', 'lifecycle-tenant')
            ->value('lock_version'));
    }

    public function test_tenant_exposes_lifecycle_actor_relations_and_casts_state_change_time(): void
    {
        $this->assertTrue(class_exists(Tenant::class), 'Tenant model is missing.');

        $creator = User::factory()->create();
        $stateChanger = User::factory()->create();
        $tenant = Tenant::query()->create([
            ...$this->requiredTenantAttributes(),
            'created_by_user_id' => $creator->id,
            'state_changed_by_user_id' => $stateChanger->id,
            'state_changed_at' => '2026-08-04 12:30:00',
        ]);

        $tenant->refresh();

        $this->assertSame($creator->id, $tenant->createdBy->id);
        $this->assertSame($stateChanger->id, $tenant->stateChangedBy->id);
        $this->assertInstanceOf(DateTimeInterface::class, $tenant->state_changed_at);
    }

    public function test_tenant_factory_creates_a_valid_tenant_with_approved_defaults(): void
    {
        $this->assertTrue(class_exists(Tenant::class), 'Tenant model is missing.');

        $tenant = Tenant::factory()->create();

        $this->assertNotSame('', $tenant->name);
        $this->assertNotSame('', $tenant->code);
        $this->assertNotSame('', $tenant->currency_code);
        $this->assertNotSame('', $tenant->language_code);
        $this->assertNotSame('', $tenant->timezone);
        $this->assertSame(TenantState::Active, $tenant->state);
        $this->assertSame(BudgetBasis::Net, $tenant->budget_basis);
        $this->assertSame('2147483648', $tenant->attachment_quota_bytes);
        $this->assertFalse($tenant->deletion_reason_required);
        $this->assertSame(1, $tenant->lock_version);
        $this->assertNotNull($tenant->created_at);
        $this->assertNotNull($tenant->updated_at);
    }

    public function test_tenant_user_and_audit_relations_are_bidirectional(): void
    {
        $this->assertTrue(class_exists(Tenant::class), 'Tenant model is missing.');

        $tenant = Tenant::query()->create($this->requiredTenantAttributes());
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $auditEvent = AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_type' => 'tenant.created',
            'correlation_id' => (string) str()->uuid(),
            'occurred_at' => now(),
        ]);

        $this->assertSame([$user->id], $tenant->users()->pluck('users.id')->all());
        $this->assertSame([$auditEvent->id], $tenant->auditEvents()->pluck('audit_events.id')->all());
        $this->assertSame($tenant->id, $user->tenant->id);
        $this->assertSame($tenant->id, $auditEvent->tenant->id);
    }

    /** @return array<string, mixed> */
    private function requiredTenantAttributes(): array
    {
        return [
            'name' => 'Lifecycle tenant',
            'code' => 'lifecycle-tenant',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function assertTenantTableExists(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'), 'Tenant schema is missing.');
    }

    /**
     * @param  callable(): mixed  $mutation
     */
    private function assertTenantDeletionRejected(callable $mutation, Tenant $tenant, string $name): void
    {
        try {
            $mutation();
            $this->fail("Tenant {$name} was accepted.");
        } catch (\LogicException $exception) {
            $this->assertSame('Tenants cannot be permanently deleted.', $exception->getMessage());
        }

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }
}
