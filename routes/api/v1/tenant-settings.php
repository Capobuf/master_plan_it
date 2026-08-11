<?php

use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware([
    RejectBearerTokens::class,
    'auth:sanctum',
    'active-user',
    'tenant-context',
    'permission-team-context',
    'active-tenant',
])->group(function (): void {
    Route::get('/tenant-settings', [TenantSettingsController::class, 'show'])
        ->middleware('application-ability:tenant-settings.view')
        ->name('api.v1.tenant-settings.show');
    Route::put('/tenant-settings', [TenantSettingsController::class, 'update'])
        ->middleware('application-ability:tenant-settings.update')
        ->name('api.v1.tenant-settings.update');
});
