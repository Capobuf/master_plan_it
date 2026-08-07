<?php

namespace Tests\Feature\Api\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
            'default_vat_rate' => '22.000000',
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
            'default_vat_rate' => '22.000000',
        ];

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants', $payload)
            ->assertCreated()
            ->assertJsonPath('data.code', 'api-tenant')
            ->assertJsonPath('data.state', 'active');
        $tenantId = $created->json('data.id');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/tenants/'.$tenantId, [
            'name' => 'API Tenant Updated',
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.name', 'API Tenant Updated');

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenantId.'/deactivate', [
            'confirmation_code' => 'api-tenant',
            'lock_version' => 2,
        ])->assertOk()->assertJsonPath('data.state', 'inactive');
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
}
