<?php

namespace Tests\Feature\Api\Auth;

use Tests\TestCase;

final class ApiAuthenticationContractTest extends TestCase
{
    public function test_authentication_contract_exposes_only_session_endpoints(): void
    {
        $routes = $this->read('routes/api/v1/foundation.php');

        foreach ([
            "Route::post('/auth/login'",
            "Route::post('/auth/logout'",
            "Route::get('/auth/me'",
            "Route::put('/auth/password'",
            "'auth:sanctum'",
            'RejectBearerTokens::class',
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }

        self::assertStringNotContainsString('createToken', $routes);
        self::assertStringNotContainsString('refresh_token', $routes);
        self::assertStringNotContainsString('oauth', $routes);
        self::assertStringNotContainsString('jwt', $routes);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$path);
    }
}
