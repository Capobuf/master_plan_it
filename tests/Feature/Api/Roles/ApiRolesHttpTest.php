<?php

namespace Tests\Feature\Api\Roles;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
            'abilities' => ['platform.users.manage'],
        ])->assertStatus(422);
    }
}
