<?php

namespace Tests\Feature\Api\Contracts;

use App\Models\Contract;
use App\Models\ContractTerm;
use App\Models\CostCenter;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
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

    public function test_contract_create_requires_ability_and_csrf_session(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->tenantUser($tenant);
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());
        try {
            $viewer->roles()->firstOrFail()->revokePermissionTo('contract.create');
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
