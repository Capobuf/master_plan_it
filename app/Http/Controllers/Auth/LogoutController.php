<?php

namespace App\Http\Controllers\Auth;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use LogicException;

final class LogoutController
{
    public function __invoke(Request $request): LogoutResponse
    {
        $guard = Filament::auth();

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Ordinary logout requires a session authentication guard.');
        }

        $guard->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
