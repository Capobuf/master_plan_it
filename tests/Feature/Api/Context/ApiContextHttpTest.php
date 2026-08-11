<?php

namespace Tests\Feature\Api\Context;

use App\Domain\Tenancy\Actions\EnterTenantContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiContextHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_tenant_user_receives_only_its_context_and_authorized_abilities(): void
    {
        $ownTenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = $this->tenantUser($ownTenant);
        $this->actingAs($user, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->withSession([
            EnterTenantContext::SESSION_KEY => $otherTenant->getKey(),
        ])->getJson('/api/v1/context');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.platformAdministrator', false)
            ->assertJsonPath('data.tenant.id', $ownTenant->getKey())
            ->assertJsonPath('data.tenant.code', $ownTenant->code);

        $this->assertContains('dashboard.view', $response->json('data.abilities'));
        $this->assertNotContains('platform.tenants.create', $response->json('data.abilities'));
    }

    public function test_administrator_enters_and_leaves_context_without_identity_switch(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator, 'web');

        $this->getJson('/api/v1/context')
            ->assertOk()
            ->assertJsonPath('data.platformAdministrator', true)
            ->assertJsonPath('data.tenant', null)
            ->assertJsonPath('data.user.id', $administrator->getKey());

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->getKey());

        $this->withHeaders($this->csrfHeaders())->getJson('/api/v1/context')
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $tenant->getKey())
            ->assertJsonPath('data.user.id', $administrator->getKey());

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/context/leave')->assertNoContent();
        $this->withHeaders($this->csrfHeaders())->getJson('/api/v1/context')->assertJsonPath('data.tenant', null);
    }

    public function test_administrator_receives_tenant_settings_abilities_only_after_entering_a_tenant(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator, 'web');

        $outsideContext = $this->getJson('/api/v1/context')->assertOk();
        $this->assertNotContains('tenant-settings.view', $outsideContext->json('data.abilities'));
        $this->assertNotContains('tenant-settings.update', $outsideContext->json('data.abilities'));

        $this->withHeaders($this->csrfHeaders())
            ->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')
            ->assertOk();

        $insideContext = $this->getJson('/api/v1/context')
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $tenant->getKey());
        $this->assertContains('tenant-settings.view', $insideContext->json('data.abilities'));
        $this->assertContains('tenant-settings.update', $insideContext->json('data.abilities'));
    }

    public function test_leave_without_context_fails_closed_with_uniform_error(): void
    {
        $administrator = $this->administrator();
        $this->actingAs($administrator, 'web');

        $response = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/context/leave');

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_CONTEXT_REQUIRED')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'correlation_id']]);
    }
}
