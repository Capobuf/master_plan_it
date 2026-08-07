<?php

namespace Tests\Feature\Api\Users;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiUsersHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_administrator_can_list_create_assign_and_deactivate_tenant_users(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $existing = $this->tenantUser($tenant);
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();
        $role = \Spatie\Permission\Models\Role::query()->where('tenant_id', $tenant->getKey())->firstOrFail();

        $this->getJson('/api/v1/users?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'links']);

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/users', [
            'name' => 'API User',
            'email' => 'api-user@example.test',
            'password' => 'Strong-password-123!',
            'roles' => [$role->getKey()],
        ])->assertCreated()->assertJsonPath('data.name', 'API User');

        $userId = (int) $created->json('data.id');
        $this->assertDatabaseHas('users', ['id' => $userId, 'tenant_id' => $tenant->getKey()]);
        $this->assertArrayNotHasKey('password', $created->json('data'));

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/users/'.$userId.'/roles', [
            'roles' => [$role->getKey()],
        ])->assertOk()->assertJsonPath('data.role_ids.0', $role->getKey());

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/users/'.$userId.'/deactivate')
            ->assertOk()->assertJsonPath('data.active', false);
    }

    public function test_foreign_user_is_not_disclosed(): void
    {
        $administrator = $this->administrator();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $foreign = $this->tenantUser($tenantB);
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenantA->getKey().'/enter')->assertOk();

        $this->getJson('/api/v1/users/'.$foreign->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_user_password_is_never_returned(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();

        $response = $this->getJson('/api/v1/users/'.$user->getKey());

        $response->assertOk()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.password_hash');
        $this->assertTrue(Hash::check('password', (string) $user->refresh()->password));
    }
}
