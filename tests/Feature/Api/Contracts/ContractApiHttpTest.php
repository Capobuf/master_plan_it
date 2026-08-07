<?php

namespace Tests\Feature\Api\Contracts;

use App\Domain\Contracts\Queries\ExpectedContractOccurrenceQuery;
use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\PlanningYear;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Api\Concerns\InteractsWithApiFoundation;
use Tests\TestCase;

final class ContractApiHttpTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithApiFoundation;

    public function test_contract_list_and_detail_return_paginated_domain_resources(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $contract = $this->contract($tenant);
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/contracts?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'links'])
            ->assertJsonPath('data.0.id', $contract->getKey())
            ->assertJsonMissingPath('data.0.deleted_by_at');

        $this->getJson('/api/v1/contracts/'.$contract->getKey())
            ->assertOk()
            ->assertJsonPath('data.terms.0.net', '100.00')
            ->assertJsonPath('data.terms.0.currency', 'EUR')
            ->assertJsonPath('data.terms.0.local_key', $contract->terms()->firstOrFail()->source_rule_key);
    }

    public function test_contract_detail_is_tenant_scoped_for_same_and_foreign_records(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $owned = $this->contract($tenant);
        $foreign = $this->contract(Tenant::factory()->create());
        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/contracts/'.$owned->getKey())
            ->assertOk()
            ->assertJsonPath('data.id', $owned->getKey());

        $this->getJson('/api/v1/contracts/'.$foreign->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_contract_create_requires_ability_and_csrf_session(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->tenantUser($tenant);
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            $role = Role::query()->where('tenant_id', $tenant->getKey())->where('name', 'Editor')->firstOrFail();
            $role->revokePermissionTo('contract.create');
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
        $this->actingAs($viewer, 'web');
        $vendor = Vendor::factory()->for($tenant)->create();
        $costCenter = CostCenter::factory()->for($tenant)->create();

        $payload = [
            'vendor_id' => $vendor->getKey(),
            'cost_center_id' => $costCenter->getKey(),
            'title' => 'Forbidden contract',
            'active' => true,
            'terms' => [[
                'local_key' => 'new-term', 'effective_start' => '2026-01-01', 'effective_end' => '2026-12-31',
                'billing_cycle' => 'monthly', 'entered_amount' => '100.00', 'amount_includes_vat' => false,
                'vat_rate' => '22.000000', 'auto_renew' => false,
            ]],
        ];

        $this->withHeaders($this->csrfHeaders())->postJson('/api/v1/contracts', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PERMISSION_DENIED');
    }

    public function test_authorized_create_update_delete_term_and_generation_controls(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $vendor = Vendor::factory()->for($tenant)->create();
        $costCenter = CostCenter::factory()->for($tenant)->create();
        PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();
        $payload = $this->payload($vendor->getKey(), $costCenter->getKey());

        $this->withHeaders($headers)->postJson('/api/v1/contracts', [
            ...$payload,
            'terms' => [[...$payload['terms'][0], 'net_amount' => '999.99']],
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $created = $this->withHeaders($headers)->postJson('/api/v1/contracts', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.title', 'Created API contract');
        $id = (int) $created->json('data.id');
        $term = $created->json('data.terms.0');

        $updated = $this->withHeaders($headers)->putJson('/api/v1/contracts/'.$id, [
            ...$payload,
            'title' => 'Updated API contract',
            'lock_version' => (int) $created->json('data.lock_version'),
            'terms' => [[...$payload['terms'][0], 'id' => $term['id'], 'local_key' => $term['local_key'], 'lock_version' => $term['lock_version']]],
        ])->assertSuccessful()->assertJsonPath('data.title', 'Updated API contract');
        $updatedTerm = $updated->json('data.terms.0');

        $this->getJson('/api/v1/contracts/999999999')->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
        $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$id.'/synchronize', ['unexpected' => true])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->withHeaders($headers)->deleteJson('/api/v1/contracts/'.$id.'/terms/'.$updatedTerm['id'], [
            'lock_version' => $updatedTerm['lock_version'],
        ])->assertNoContent();
        $this->withHeaders($headers)->deleteJson('/api/v1/contracts/'.$id, [
            'lock_version' => (int) $updated->json('data.lock_version'),
        ])->assertNoContent();
    }

    public function test_generate_resume_and_resume_and_generate_are_authorized_operation_paths(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->tenantUser($tenant);
        $contract = $this->contract($tenant);
        PlanningYear::factory()->for($tenant)->create(['year_label' => 2026]);
        $this->actingAs($user, 'web');
        $headers = $this->csrfHeaders();
        $term = $contract->terms()->firstOrFail();

        $generated = $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$contract->getKey().'/generate/2026')
            ->assertSuccessful()->assertJsonPath('data.currency', 'EUR');
        $this->withHeaders($headers)->deleteJson('/api/v1/contracts/'.$contract->getKey().'/generated-expenses/'.$generated->json('data.id'), [
            'lock_version' => $generated->json('data.lock_version'),
            'allow_regeneration' => true,
        ])->assertNoContent();
        $sourceKey = (string) app(ExpectedContractOccurrenceQuery::class)->forContract($contract)[0]->sourceKey;

        $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$contract->getKey().'/occurrences/'.$sourceKey.'/suppress', ['reason' => 'Not this cycle'])->assertNoContent();
        $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$contract->getKey().'/occurrences/'.$sourceKey.'/resume')->assertNoContent();
        $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$contract->getKey().'/occurrences/'.$sourceKey.'/suppress', ['reason' => 'Defer again'])->assertNoContent();
        $this->withHeaders($headers)->postJson('/api/v1/contracts/'.$contract->getKey().'/occurrences/'.$sourceKey.'/resume-and-generate')
            ->assertSuccessful()->assertJsonPath('data.source_key', $sourceKey);
        $this->assertNotNull($generated->json('data.id'));
    }

    /** @return array<string, mixed> */
    private function payload(int $vendorId, int $costCenterId): array
    {
        return [
            'vendor_id' => $vendorId,
            'cost_center_id' => $costCenterId,
            'title' => 'Created API contract',
            'description' => null,
            'active' => true,
            'renewal_date' => null,
            'renewal_notice_days' => null,
            'renewal_notes' => null,
            'terms' => [[
                'local_key' => 'term-input', 'effective_start' => '2026-01-01', 'effective_end' => '2026-12-31',
                'billing_cycle' => 'monthly', 'quantity' => null, 'unit_price' => null, 'entered_amount' => '100.00',
                'amount_includes_vat' => false, 'vat_rate' => '22.000000', 'auto_renew' => false,
            ]],
        ];
    }

    private function contract(Tenant $tenant): Contract
    {
        $vendor = Vendor::factory()->for($tenant)->create();
        $costCenter = CostCenter::factory()->for($tenant)->create();
        $contract = Contract::query()->create([
            'tenant_id' => $tenant->getKey(), 'vendor_id' => $vendor->getKey(), 'cost_center_id' => $costCenter->getKey(),
            'title' => 'API contract', 'description' => null, 'active' => true, 'lock_version' => 1,
        ]);
        ContractTerm::query()->create([
            'tenant_id' => $tenant->getKey(), 'contract_id' => $contract->getKey(), 'source_rule_key' => (string) Str::uuid(),
            'effective_start' => '2026-01-01', 'effective_end' => '2026-12-31', 'billing_cycle' => 'monthly',
            'quantity' => null, 'unit_price' => null, 'entered_amount' => '100.00', 'amount_includes_vat' => false,
            'vat_rate' => '22.000000', 'net_amount' => '100.00', 'vat_amount' => '22.00', 'gross_amount' => '122.00',
            'auto_renew' => false, 'lock_version' => 1,
        ]);

        return $contract;
    }
}
