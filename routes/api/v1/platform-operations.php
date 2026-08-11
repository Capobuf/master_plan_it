<?php

use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlatformOverviewController;
use App\Http\Controllers\Api\V1\PlatformSettingsController;
use App\Http\Controllers\Api\V1\TenantSettingsController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware([
    RejectBearerTokens::class, 'auth:sanctum', 'active-user', 'tenant-context',
    'permission-team-context', 'active-tenant',
])->group(function (): void {
    Route::get('/tenant-settings', [TenantSettingsController::class, 'show'])
        ->middleware('application-ability:tenant-settings.view')->name('api.v1.tenant-settings.show');
    Route::put('/tenant-settings', [TenantSettingsController::class, 'update'])
        ->middleware('application-ability:tenant-settings.update')->name('api.v1.tenant-settings.update');
    Route::get('/audit-events', [AuditController::class, 'tenant'])
        ->middleware('application-ability:audit.view')->name('api.v1.audit-events.index');
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('application-ability:notification.view')->name('api.v1.notifications.index');
});

Route::middleware([RejectBearerTokens::class, 'auth:sanctum', 'active-user'])->prefix('platform')->group(function (): void {
    Route::get('/settings', [PlatformSettingsController::class, 'show'])
        ->middleware('application-ability:platform.settings.manage')->name('api.v1.platform.settings.show');
    Route::put('/settings/audit-retention', [PlatformSettingsController::class, 'updateRetention'])
        ->middleware('application-ability:platform.settings.manage')->name('api.v1.platform.settings.audit-retention');
    Route::get('/audit-events', [AuditController::class, 'platform'])
        ->middleware('application-ability:platform.audit.view-global')->name('api.v1.platform.audit-events.index');
    Route::get('/overview', PlatformOverviewController::class)
        ->middleware('application-ability:platform.settings.manage')->name('api.v1.platform.overview');
});
