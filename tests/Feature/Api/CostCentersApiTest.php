<?php

namespace Tests\Feature\Api;

use App\Models\CostCenter;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class CostCentersApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_cost_center_tree_is_nested_and_does_not_expose_tenant_persistence(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $root = CostCenter::factory()->for($tenant)->create(['name' => 'Root']);
        CostCenter::factory()->for($tenant)->create(['name' => 'Child', 'parent_id' => $root->getKey()]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/cost-centers/tree')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Root')
            ->assertJsonPath('data.0.depth', 0)
            ->assertJsonPath('data.0.children.0.name', 'Child')
            ->assertJsonMissingPath('data.0.tenant_id');
    }

    public function test_cost_center_create_and_update_require_csrf_and_lock_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/cost-centers', ['name' => 'Operations'])
            ->assertCreated();
        $id = $created->json('data.id');

        $this->withHeaders($headers)->putJson('/api/v1/cost-centers/'.$id, [
            'name' => 'Operations updated',
            'parent_id' => null,
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.name', 'Operations updated');
    }
}
