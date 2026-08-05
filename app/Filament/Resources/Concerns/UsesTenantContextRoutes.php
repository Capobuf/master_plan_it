<?php

namespace App\Filament\Resources\Concerns;

use App\Http\Middleware\ApplyTenantPresentationContext;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetPermissionTeamContext;
use Filament\Panel;

trait UsesTenantContextRoutes
{
    /** @return array<string> */
    public static function getRouteMiddleware(Panel $panel): array
    {
        return [
            ResolveTenantContext::class,
            SetPermissionTeamContext::class,
            EnsureTenantIsActive::class,
            ApplyTenantPresentationContext::class,
        ];
    }
}
