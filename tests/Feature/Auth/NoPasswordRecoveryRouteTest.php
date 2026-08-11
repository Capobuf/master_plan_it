<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class NoPasswordRecoveryRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_registration_and_password_recovery_actions_are_not_registered(): void
    {
        foreach ([
            'register',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
        ] as $routeName) {
            $this->assertFalse(RouteFacade::has($routeName));
        }

        $forbiddenUris = ['register', 'forgot-password', 'reset-password', 'reset-password/{token}'];
        $registeredUris = collect(RouteFacade::getRoutes()->getRoutes())
            ->map(static fn ($route): string => $route->uri())
            ->all();

        foreach ($forbiddenUris as $uri) {
            $this->assertNotContains($uri, $registeredUris);
        }
    }
}
