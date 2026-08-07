<?php

namespace Tests\Feature\Api\Contracts;

use Tests\TestCase;

final class ContractApiContractTest extends TestCase
{
    public function test_contract_api_is_operation_oriented_and_uses_safe_resources(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 4).'/routes/api/v1/contracts.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 4).'/app/Http/Controllers/Api/V1/ContractController.php');
        $resource = (string) file_get_contents(dirname(__DIR__, 4).'/app/Http/Resources/Api/V1/ContractResource.php');

        foreach ([
            "Route::get('/', [ContractController::class, 'index'])",
            "Route::get('/{contract}', [ContractController::class, 'show'])",
            "Route::post('/', [ContractController::class, 'store'])",
            "Route::put('/{contract}', [ContractController::class, 'update'])",
            "Route::delete('/{contract}', [ContractController::class, 'destroy'])",
            "Route::post('/{contract}/synchronize'",
            "Route::post('/{contract}/generate/{year}'",
            "Route::post('/{contract}/occurrences/{sourceKey}/resume'",
            "Route::post('/{contract}/occurrences/{sourceKey}/resume-and-generate'",
            "Route::delete('/{contract}/terms/{term}'",
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }

        foreach (['vendor_id', 'cost_center_id', 'terms', 'occurrences', 'generated_expenses', 'lock_version'] as $field) {
            self::assertStringContainsString("'{$field}'", $resource);
        }

        self::assertStringContainsString('rejectUnexpected', $controller);
        self::assertStringNotContainsString('MoneyFormatter', $controller);
        self::assertStringNotContainsString("deleted_by_at'", $resource);
    }
}
