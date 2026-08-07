<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('api.v1.auth.login');

    Route::middleware(['auth:sanctum', 'active-user'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::put('/auth/password', [AuthController::class, 'password'])->name('api.v1.auth.password');
        Route::get('/context', [ContextController::class, 'show'])->name('api.v1.context.show');

        Route::middleware('application-ability:platform.tenants.view')->group(function (): void {
            Route::post('/tenants/{tenant}/enter', [TenantController::class, 'enter'])
                ->whereNumber('tenant')
                ->name('api.v1.tenants.enter');
            Route::post('/context/leave', [TenantController::class, 'leave'])->name('api.v1.context.leave');
        });

        Route::prefix('/tenants')->middleware('application-ability:platform.tenants.view')->group(function (): void {
            Route::get('/', [TenantController::class, 'index'])->name('api.v1.tenants.index');
            Route::get('/{tenant}', [TenantController::class, 'show'])->whereNumber('tenant')->name('api.v1.tenants.show');
        });

        Route::prefix('/tenants')->group(function (): void {
            Route::post('/', [TenantController::class, 'store'])
                ->middleware('application-ability:platform.tenants.create')
                ->name('api.v1.tenants.store');
            Route::put('/{tenant}', [TenantController::class, 'update'])
                ->middleware('application-ability:platform.tenants.update')
                ->whereNumber('tenant')
                ->name('api.v1.tenants.update');
            Route::post('/{tenant}/deactivate', [TenantController::class, 'deactivate'])
                ->middleware('application-ability:platform.tenants.deactivate')
                ->whereNumber('tenant')
                ->name('api.v1.tenants.deactivate');
            Route::post('/{tenant}/reactivate', [TenantController::class, 'reactivate'])
                ->middleware('application-ability:platform.tenants.reactivate')
                ->whereNumber('tenant')
                ->name('api.v1.tenants.reactivate');
        });
    });
});
