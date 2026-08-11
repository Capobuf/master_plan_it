<?php

namespace Tests\Feature\Api\Roles;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ApiRolesHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_administrator_can_manage_roles_and_read_only_assignable_abilities(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();

        $abilities = $this->getJson('/api/v1/abilities')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['name', 'label']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
        foreach ($abilities->json('data') as $ability) {
            self::assertFalse(str_starts_with($ability['name'], 'platform.'));
        }

        $created = $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'API role',
            'abilities' => ['planning-year.view'],
        ])->assertCreated()->assertJsonPath('data.name', 'API role');
        $roleId = (int) $created->json('data.id');

        $this->withHeaders($this->csrfHeaders())->putJson('/api/v1/roles/'.$roleId, [
            'name' => 'API role updated',
            'abilities' => ['planning-year.view', 'planning-year.create'],
        ])->assertOk()->assertJsonPath('data.abilities.1', 'planning-year.view');

        $this->withHeaders($this->csrfHeaders())->deleteJson('/api/v1/roles/'.$roleId)
            ->assertNoContent();
    }

    public function test_protected_abilities_cannot_be_assigned_to_tenant_roles(): void
    {
        $administrator = $this->administrator();
        $tenant = Tenant::factory()->create();
        $this->actingAs($administrator, 'web');
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/tenants/'.$tenant->getKey().'/enter')->assertOk();

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'Invalid role',
            'abilities' => ['platform.tenants.view'],
        ])->assertStatus(422);
    }

    public function test_tenant_roles_view_is_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->tenantRoleActor($tenant, ['tenant-roles.view']);
        $this->actingAs($viewer, 'web');

        $this->getJson('/api/v1/roles')->assertOk();
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'Denied role',
            'abilities' => ['dashboard.view'],
        ])->assertForbidden()->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_tenant_roles_manage_implies_read_and_can_assign_only_catalogued_tenant_abilities(): void
    {
        $tenant = Tenant::factory()->create();
        $manager = $this->tenantRoleActor($tenant, ['tenant-roles.manage']);
        $this->actingAs($manager, 'web');

        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/abilities')->assertOk()->assertJsonFragment(['name' => 'tenant-settings.view']);
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'Tenant operations',
            'abilities' => ['tenant-settings.view', 'tenant-users.manage', 'tenant-roles.view'],
        ])->assertCreated();
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'Arbitrary permission',
            'abilities' => ['tenant.custom.unregistered'],
        ])->assertUnprocessable();
        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/roles', [
            'name' => 'Platform escalation',
            'abilities' => ['platform.tenants.update'],
        ])->assertUnprocessable();
    }

    /** @param list<string> $abilities */
    private function tenantRoleActor(Tenant $tenant, array $abilities): User
    {
        $user = $this->tenantUser($tenant);
        $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Editor')->firstOrFail();
        $role->syncPermissions($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
