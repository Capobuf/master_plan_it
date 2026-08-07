<?php

namespace Tests\Feature\Api\Tenancy;

use Tests\TestCase;

final class ApiTenancyContractTest extends TestCase
{
    public function test_tenant_lifecycle_contract_is_operation_oriented_and_paginated(): void
    {
        $routes = $this->read('routes/api/v1/foundation.php');
        $resource = $this->read('app/Http/Resources/Api/V1/TenantResource.php');

        foreach ([
            "Route::get('/', [TenantController::class, 'index'])",
            "Route::get('/{tenant}', [TenantController::class, 'show'])",
            "Route::post('/', [TenantController::class, 'store'])",
            "Route::put('/{tenant}', [TenantController::class, 'update'])",
            "Route::post('/{tenant}/deactivate'",
            "Route::post('/{tenant}/reactivate'",
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }

        foreach (['id', 'name', 'code', 'currency_code', 'default_vat_rate', 'state', 'lock_version'] as $field) {
            self::assertStringContainsString("'$field'", $resource);
        }

        self::assertStringContainsString('->paginate(', $this->read('app/Http/Controllers/Api/V1/TenantController.php'));
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$path);
    }
}
