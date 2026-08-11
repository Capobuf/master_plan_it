<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class VendorsApiTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_vendor_collection_is_paginated_and_whitelisted(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        Vendor::factory()->for($tenant)->count(3)->create();
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/vendors?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'links'])
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.tenant_id');
    }

    public function test_vendor_mutations_use_session_csrf_and_tenant_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $foreign = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $other = Vendor::factory()->for($foreign)->create();
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/vendors', ['name' => 'API supplier'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'API supplier');

        $this->getJson('/api/v1/vendors/'.$other->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->withHeaders($headers)->putJson('/api/v1/vendors/'.$created->json('data.id'), [
            'name' => 'Updated supplier',
            'lock_version' => 1,
        ])->assertOk()->assertJsonPath('data.name', 'Updated supplier');
    }
}
