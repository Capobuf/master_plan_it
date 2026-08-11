<?php

namespace Tests\Feature\Api\Contracts;

use Tests\TestCase;

final class ApiFoundationContractTest extends TestCase
{
    public function test_foundation_routes_are_versioned_and_use_sanctum_sessions(): void
    {
        $foundation = $this->read('routes/api/v1/foundation.php');

        self::assertStringContainsString("Route::post('/auth/login'", $foundation);
        self::assertStringContainsString("Route::post('/auth/logout'", $foundation);
        self::assertStringContainsString("Route::get('/auth/me'", $foundation);
        self::assertStringContainsString("Route::put('/auth/password'", $foundation);
        self::assertStringContainsString("Route::get('/context'", $foundation);
        self::assertStringContainsString("'auth:sanctum'", $foundation);
    }

    public function test_no_v2_or_bearer_token_endpoint_is_registered(): void
    {
        $routes = $this->read('routes/api/v1/foundation.php');
        $master = $this->read('routes/api.php');

        self::assertStringNotContainsString('/api/v2', $routes.$master);
        self::assertStringNotContainsString('createToken', $routes.$master);
        self::assertStringContainsString("glob(__DIR__.'/api/v1/*.php')", $master);
    }

    public function test_sanctum_csrf_cookie_is_the_only_unversioned_api_infrastructure_route(): void
    {
        $bootstrap = $this->read('bootstrap/app.php');
        $composer = $this->read('composer.json');

        self::assertStringContainsString('$middleware->statefulApi();', $bootstrap);
        self::assertStringContainsString('"laravel/sanctum": "4.3.3"', $composer);
        self::assertStringContainsString("api: __DIR__.'/../routes/api.php'", $bootstrap);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$path);
    }
}
