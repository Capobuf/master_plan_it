<?php

namespace Tests\Feature\PlatformOperations;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditProperties;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class PlatformOperationsHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_tenant_audit_and_notifications_are_permission_and_identity_scoped(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $actor = $this->tenantUser($tenant);
        $other = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        app(AuditRecorder::class)->record('owned.event', (string) str()->uuid(), new AuditProperties([]), $actor, (int) $tenant->getKey());
        app(AuditRecorder::class)->record('foreign.event', (string) str()->uuid(), new AuditProperties([]), null, (int) $foreignTenant->getKey());
        DB::table('notifications')->insert([
            [
                'id' => (string) str()->uuid(), 'type' => 'OperationalAlert',
                'notifiable_type' => $actor->getMorphClass(), 'notifiable_id' => $actor->getKey(),
                'data' => json_encode(['message' => 'Owned notification', 'nested' => ['token' => 'hidden']], JSON_THROW_ON_ERROR),
                'read_at' => null, 'deduplication_key' => 'owned', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => (string) str()->uuid(), 'type' => 'OperationalAlert',
                'notifiable_type' => $other->getMorphClass(), 'notifiable_id' => $other->getKey(),
                'data' => json_encode(['message' => 'Foreign notification'], JSON_THROW_ON_ERROR),
                'read_at' => null, 'deduplication_key' => 'foreign', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $this->actingAs($actor, 'web');

        $this->getJson('/api/v1/audit-events?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['event_type' => 'owned.event'])
            ->assertJsonMissing(['event_type' => 'foreign.event']);
        $this->getJson('/api/v1/notifications?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['message' => 'Owned notification'])
            ->assertJsonMissing(['message' => 'Foreign notification'])
            ->assertJsonMissing(['token' => 'hidden']);
    }

    public function test_global_overview_audit_and_retention_are_administrator_only(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        PlatformSetting::query()->create(['id' => 1, 'audit_retention_months' => 24, 'lock_version' => 1]);
        app(AuditRecorder::class)->record('tenant.activity', (string) str()->uuid(), new AuditProperties([]), null, (int) $tenant->getKey());
        $this->actingAs($administrator, 'web');
        $headers = $this->csrfHeaders();

        $this->getJson('/api/v1/platform/overview')
            ->assertOk()
            ->assertJsonFragment(['tenant_id' => $tenant->getKey(), 'name' => $tenant->name])
            ->assertJsonMissingPath('data.0.net')
            ->assertJsonMissingPath('data.0.gross');
        $this->getJson('/api/v1/platform/audit-events?tenant_id='.$tenant->getKey())
            ->assertOk()
            ->assertJsonFragment(['event_type' => 'tenant.activity']);
        $this->getJson('/api/v1/platform/settings')
            ->assertOk()
            ->assertJsonPath('data.audit_retention_months', 24);
        $this->withHeaders($headers)->putJson('/api/v1/platform/settings/audit-retention', [
            'audit_retention_months' => 36,
            'lock_version' => 1,
            'confirmation' => null,
        ])->assertOk()->assertJsonPath('data.audit_retention_months', 36);

    }

    public function test_tenant_user_cannot_access_platform_operations(): void
    {
        $tenantUser = $this->tenantUser(Tenant::factory()->create());
        $this->actingAs($tenantUser, 'web');

        $this->getJson('/api/v1/platform/overview')->assertForbidden();
        $this->getJson('/api/v1/platform/audit-events')->assertForbidden();
        $this->getJson('/api/v1/platform/settings')->assertForbidden();
    }
}
