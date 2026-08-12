<?php

namespace Tests\Feature\PlatformOperations;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TenantEconomicBasisTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_approval_has_a_persisted_atomic_economic_basis_lock_timestamp(): void
    {
        $tenant = Tenant::factory()->create(['budget_basis' => 'net']);
        $this->assertTrue(Schema::hasColumn('tenants', 'economic_basis_locked_at'));
        $this->assertNull($tenant->economic_basis_locked_at);
        $approval = file_get_contents(base_path('app/Domain/Budget/Actions/ApplyBudgetApproval.php'));
        $this->assertStringContainsString('economic_basis_locked_at', $approval);
    }

    public function test_target_settings_action_has_an_explicit_noop_audit_contract(): void
    {
        $contents = file_get_contents(base_path('app/Domain/Tenancy/Actions/UpdateTenantSettings.php'));
        $this->assertStringContainsString('economic_basis_locked_at', $contents);
        $this->assertStringContainsString('tenant.settings.updated', $contents);
    }
}
