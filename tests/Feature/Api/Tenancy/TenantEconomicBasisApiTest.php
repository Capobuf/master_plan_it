<?php

namespace Tests\Feature\Api\Tenancy;

use Tests\TestCase;

final class TenantEconomicBasisApiTest extends TestCase
{
    public function test_settings_resource_uses_the_public_economic_basis_names_and_hides_legacy_storage_name(): void
    {
        $contents = file_get_contents(base_path('app/Http/Resources/Api/V1/TenantSettingsResource.php'));
        $this->assertStringContainsString("'economic_basis'", $contents);
        $this->assertStringContainsString("'economic_basis_locked_at'", $contents);
        $this->assertStringNotContainsString("'budget_basis' =>", $contents);
    }
}
