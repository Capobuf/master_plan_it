<?php

namespace Tests\Feature\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Auth\Pages\Register;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class NoPasswordRecoveryRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_registration_and_password_recovery_actions_are_not_registered(): void
    {
        $forbiddenActions = [
            Register::class,
            RequestPasswordReset::class,
            ResetPassword::class,
        ];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $this->assertInstanceOf(Route::class, $route);
            $this->assertNotContains($route->getActionName(), $forbiddenActions);
        }

        foreach ([
            'register',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'filament.admin.auth.register',
            'filament.admin.auth.password-reset.request',
            'filament.admin.auth.password-reset.reset',
        ] as $routeName) {
            $this->assertFalse(RouteFacade::has($routeName));
        }
    }
}
