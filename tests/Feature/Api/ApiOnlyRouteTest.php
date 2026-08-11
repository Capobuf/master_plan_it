<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiOnlyRouteTest extends TestCase
{
    public function test_application_routes_are_api_or_infrastructure_only(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            if (in_array($uri, ['up', 'sanctum/csrf-cookie'], true) || str_starts_with($uri, 'storage/')) {
                continue;
            }

            self::assertStringStartsWith('api/', $uri, "Unexpected non-API route: {$route->uri()}");
        }

        self::assertFalse(Route::has('login'));
        self::assertFalse(Route::has('home'));
        self::assertFalse(Route::has('operational.index'));
    }
}
