<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\AuthenticatedHomeController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\ResolveOptionalTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::middleware('active-user')->group(function (): void {
        Route::middleware(ResolveOptionalTenantContext::class)->group(function (): void {
            Route::get('/', AuthenticatedHomeController::class)->name('home');
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
                ->name('profile.password.update');
        });

        Route::prefix('/platform')->name('platform.')->group(function (): void {
            Route::get('/tenants', [TenantController::class, 'index'])
                ->middleware('application-ability:platform.tenants.view')
                ->name('tenants.index');
            Route::get('/tenants/create', [TenantController::class, 'create'])
                ->middleware('application-ability:platform.tenants.create')
                ->name('tenants.create');
            Route::post('/tenants', [TenantController::class, 'store'])
                ->middleware('application-ability:platform.tenants.create')
                ->name('tenants.store');
            Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])
                ->middleware('application-ability:platform.tenants.update')
                ->whereNumber('tenant')
                ->name('tenants.edit');
            Route::put('/tenants/{tenant}', [TenantController::class, 'update'])
                ->middleware('application-ability:platform.tenants.update')
                ->whereNumber('tenant')
                ->name('tenants.update');
            Route::post('/tenants/{tenant}/deactivate', [TenantController::class, 'deactivate'])
                ->middleware('application-ability:platform.tenants.deactivate')
                ->whereNumber('tenant')
                ->name('tenants.deactivate');
            Route::post('/tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])
                ->middleware('application-ability:platform.tenants.reactivate')
                ->whereNumber('tenant')
                ->name('tenants.reactivate');
            Route::post('/tenants/{tenant}/enter', [TenantController::class, 'enter'])
                ->middleware('application-ability:platform.tenants.view')
                ->whereNumber('tenant')
                ->name('tenants.enter');
            Route::post('/tenant/leave', [TenantController::class, 'leave'])
                ->middleware('application-ability:platform.tenants.view')
                ->name('tenant.leave');
        });
    });
});
