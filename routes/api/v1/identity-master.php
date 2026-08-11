<?php

use App\Http\Controllers\Api\V1\PlanningYearController;
use App\Http\Controllers\Api\V1\TenantRoleController;
use App\Http\Controllers\Api\V1\TenantUserController;
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
    Route::prefix('users')->group(function (): void {
        Route::get('/', [TenantUserController::class, 'index'])->middleware('application-ability:tenant-users.view')->name('api.v1.users.index');
        Route::get('/{user}', [TenantUserController::class, 'show'])->middleware('application-ability:tenant-users.view')->whereNumber('user')->name('api.v1.users.show');
        Route::post('/', [TenantUserController::class, 'store'])->middleware('application-ability:tenant-users.manage')->name('api.v1.users.store');
        Route::put('/{user}', [TenantUserController::class, 'update'])->middleware('application-ability:tenant-users.manage')->whereNumber('user')->name('api.v1.users.update');
        Route::put('/{user}/roles', [TenantUserController::class, 'updateRoles'])->middleware('application-ability:tenant-users.manage')->whereNumber('user')->name('api.v1.users.roles');
        Route::post('/{user}/deactivate', [TenantUserController::class, 'deactivate'])->middleware('application-ability:tenant-users.manage')->whereNumber('user')->name('api.v1.users.deactivate');
        Route::put('/{user}/password', [TenantUserController::class, 'resetPassword'])->middleware('application-ability:tenant-users.manage')->whereNumber('user')->name('api.v1.users.password');
    });

    Route::get('/user-role-options', [TenantUserController::class, 'roleOptions'])
        ->middleware('application-ability:tenant-users.manage')->name('api.v1.user-role-options.index');

    Route::prefix('roles')->group(function (): void {
        Route::get('/', [TenantRoleController::class, 'index'])->middleware('application-ability:tenant-roles.view')->name('api.v1.roles.index');
        Route::get('/{role}', [TenantRoleController::class, 'show'])->middleware('application-ability:tenant-roles.view')->whereNumber('role')->name('api.v1.roles.show');
        Route::post('/', [TenantRoleController::class, 'store'])->middleware('application-ability:tenant-roles.manage')->name('api.v1.roles.store');
        Route::put('/{role}', [TenantRoleController::class, 'update'])->middleware('application-ability:tenant-roles.manage')->whereNumber('role')->name('api.v1.roles.update');
        Route::delete('/{role}', [TenantRoleController::class, 'destroy'])->middleware('application-ability:tenant-roles.manage')->whereNumber('role')->name('api.v1.roles.destroy');
    });

    Route::get('/abilities', [TenantRoleController::class, 'abilities'])
        ->middleware('application-ability:tenant-roles.manage')
        ->name('api.v1.abilities.index');

    Route::prefix('planning-years')->group(function (): void {
        Route::get('/', [PlanningYearController::class, 'index'])
            ->middleware('application-ability:planning-year.view')
            ->name('api.v1.planning-years.index');
        Route::post('/', [PlanningYearController::class, 'store'])
            ->middleware('application-ability:planning-year.create')
            ->name('api.v1.planning-years.store');
        Route::post('/{planningYear}/deactivate', [PlanningYearController::class, 'deactivate'])
            ->middleware('application-ability:planning-year.deactivate')
            ->whereNumber('planningYear')
            ->name('api.v1.planning-years.deactivate');
        Route::post('/{planningYear}/reactivate', [PlanningYearController::class, 'reactivate'])
            ->middleware('application-ability:planning-year.reactivate')
            ->whereNumber('planningYear')
            ->name('api.v1.planning-years.reactivate');
    });
});
