<?php

namespace Tests\Feature\Api\Tenancy;

use App\Models\AuditEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiTenancyHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_administrator_tenant_list_uses_standard_pagination_contract(): void
    {
        $administrator = $this->administrator();
        Tenant::factory()->count(3)->create();
        $this->actingAs($administrator, 'web');

        $response = $this->getJson('/api/v1/tenants?per_page=2');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_tenant_user_cannot_use_platform_tenant_lifecycle_operations(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants', [
            'name' => 'Forbidden tenant',
            'code' => 'forbidden-tenant',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
        ]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'PERMISSION_DENIED');
        $this->assertDatabaseMissing('tenants', ['code' => 'forbidden-tenant']);
    }

    public function test_administrator_can_create_update_and_deactivate_a_tenant(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web');
        $payload = [
            'name' => 'API Tenant',
            'code' => 'api-tenant',
            'currency_code' => 'EUR',
            'language_code' => 'it',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
        ];

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'API Tenant')
            ->assertJsonPath('data.code', 'api-tenant')
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonPath('data.language_code', 'it')
            ->assertJsonPath('data.timezone', 'Europe/Rome')
            ->assertJsonPath('data.default_vat_rate', '22.00')
            ->assertJsonPath('data.state', 'active');
        $tenantId = $created->json('data.id');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenants/'.$tenantId, [
            'code' => 'api-tenant-updated',
            'currency_code' => 'USD',
            'language_code' => 'en',
            'lock_version' => 1,
        ])->assertOk()
            ->assertJsonPath('data.name', 'API Tenant')
            ->assertJsonPath('data.code', 'api-tenant-updated')
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.language_code', 'en')
            ->assertJsonPath('data.timezone', 'Europe/Rome')
            ->assertJsonPath('data.default_vat_rate', '22.00');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenantId.'/deactivate', [
            'confirmation_code' => 'api-tenant-updated',
            'lock_version' => 2,
        ])->assertOk()->assertJsonPath('data.state', 'inactive');
    }

    #[DataProvider('settingsOwnedUpdateFields')]
    public function test_global_update_rejects_each_settings_owned_field_atomically_without_audit(
        string $field,
        mixed $value,
    ): void {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'name' => 'Original operational name',
            'code' => 'global-update-target',
            'timezone' => 'Europe/Rome',
            'default_vat_rate' => '22.00',
            'budget_basis' => 'net',
            'deletion_reason_required' => false,
            'lock_version' => 5,
        ]);
        $this->actingAs($administrator, 'web');
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();
        $original = (array) DB::table('tenants')->where('id', $tenant->getKey())->firstOrFail();

        $response = $this->withHeaders([
            ...$this->csrfHeaders(),
            'X-Correlation-ID' => $correlationId,
        ])->putJson('/api/v1/tenants/'.$tenant->getKey(), [
            'code' => 'must-not-be-persisted',
            $field => $value,
            'lock_version' => 5,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey($field, $response->json('error.fields'));
        $tenant->refresh();
        $this->assertSame(
            $original,
            (array) DB::table('tenants')->where('id', $tenant->getKey())->firstOrFail(),
        );
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_global_update_stale_version_on_allowed_field_has_no_side_effects(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create([
            'code' => 'stale-global-update',
            'lock_version' => 4,
        ]);
        $this->actingAs($administrator, 'web');
        $correlationId = (string) str()->uuid();
        $auditCount = AuditEvent::query()->count();

        $this->withHeaders([
            ...$this->csrfHeaders(),
            'X-Correlation-ID' => $correlationId,
        ])->putJson('/api/v1/tenants/'.$tenant->getKey(), [
            'code' => 'stale-global-update-attempt',
            'lock_version' => 3,
        ])->assertConflict()->assertJsonPath('error.code', 'STALE_VERSION');

        $tenant->refresh();
        $this->assertSame('stale-global-update', $tenant->code);
        $this->assertSame(4, $tenant->lock_version);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseMissing('audit_events', ['correlation_id' => $correlationId]);
    }

    public function test_protected_tenant_404_is_safe_and_correlated(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web');

        $response = $this->withHeader('X-Correlation-ID', '2aa2f287-fb75-4c19-a1d0-a046f739507a')
            ->getJson('/api/v1/tenants/999999999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.correlation_id', '2aa2f287-fb75-4c19-a1d0-a046f739507a');
        $this->assertSame('2aa2f287-fb75-4c19-a1d0-a046f739507a', $response->headers->get('X-Correlation-ID'));
    }

    /** @return array<string, array{string, mixed}> */
    public static function settingsOwnedUpdateFields(): array
    {
        return [
            'name' => ['name', 'Rejected operational name'],
            'timezone' => ['timezone', 'UTC'],
            'default VAT rate' => ['default_vat_rate', '10.50'],
            'budget basis' => ['budget_basis', 'gross'],
            'deletion reason requirement' => ['deletion_reason_required', true],
        ];
    }
}
