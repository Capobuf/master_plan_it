<?php

namespace Tests\Feature\Api\Context;

use Tests\TestCase;

final class ApiContextContractTest extends TestCase
{
    public function test_context_contract_exposes_context_and_administrator_switch_operations(): void
    {
        $routes = $this->read('routes/api/v1/foundation.php');

        foreach ([
            "Route::get('/context'",
            "Route::post('/context/leave'",
            "Route::post('/tenants/{tenant}/enter'",
            "'application-ability:platform.tenants.view'",
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }

        $resource = $this->read('app/Http/Resources/Api/V1/ContextResource.php');
        foreach (['user', 'platformAdministrator', 'tenant', 'abilities'] as $field) {
            self::assertStringContainsString("'$field'", $resource);
        }
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$path);
    }
}
