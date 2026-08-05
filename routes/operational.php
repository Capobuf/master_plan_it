<?php

use App\Http\Controllers\Operational\CostCenterController;
use App\Http\Controllers\Operational\DashboardController;
use App\Http\Controllers\Operational\ExpenseController;
use App\Http\Controllers\Operational\PlanningYearController;
use App\Http\Controllers\Operational\TenantRoleController;
use App\Http\Controllers\Operational\TenantUserController;
use App\Http\Controllers\Operational\VendorController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active-user',
    'tenant-context',
    'permission-team-context',
    'active-tenant',
    'tenant-presentation',
])->prefix('/operational')->name('operational.')->group(function (): void {
    Route::get('/', DashboardController::class)
        ->middleware('application-ability:dashboard.view')
        ->name('index');

    Route::prefix('/users')->name('users.')
        ->middleware('application-ability:platform.users.manage')
        ->group(function (): void {
            Route::get('/', [TenantUserController::class, 'index'])->name('index');
            Route::get('/create', [TenantUserController::class, 'create'])->name('create');
            Route::post('/', [TenantUserController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [TenantUserController::class, 'edit'])->whereNumber('user')->name('edit');
            Route::put('/{user}', [TenantUserController::class, 'update'])->whereNumber('user')->name('update');
            Route::post('/{user}/deactivate', [TenantUserController::class, 'deactivate'])
                ->whereNumber('user')
                ->name('deactivate');
            Route::put('/{user}/password', [TenantUserController::class, 'resetPassword'])
                ->whereNumber('user')
                ->name('password.update');
        });

    Route::prefix('/roles')->name('roles.')
        ->middleware('application-ability:platform.roles.manage')
        ->group(function (): void {
            Route::get('/', [TenantRoleController::class, 'index'])->name('index');
            Route::get('/create', [TenantRoleController::class, 'create'])->name('create');
            Route::post('/', [TenantRoleController::class, 'store'])->name('store');
            Route::get('/{role}/edit', [TenantRoleController::class, 'edit'])->whereNumber('role')->name('edit');
            Route::put('/{role}', [TenantRoleController::class, 'update'])->whereNumber('role')->name('update');
            Route::delete('/{role}', [TenantRoleController::class, 'destroy'])->whereNumber('role')->name('destroy');
        });

    Route::prefix('/planning-years')->name('planning-years.')->group(function (): void {
        Route::get('/', [PlanningYearController::class, 'index'])
            ->middleware('application-ability:planning-year.view')
            ->name('index');
        Route::post('/', [PlanningYearController::class, 'store'])
            ->middleware('application-ability:planning-year.create')
            ->name('store');
        Route::post('/{planningYear}/deactivate', [PlanningYearController::class, 'deactivate'])
            ->middleware('application-ability:planning-year.deactivate')
            ->whereNumber('planningYear')
            ->name('deactivate');
        Route::post('/{planningYear}/reactivate', [PlanningYearController::class, 'reactivate'])
            ->middleware('application-ability:planning-year.reactivate')
            ->whereNumber('planningYear')
            ->name('reactivate');
    });

    Route::prefix('/vendors')->name('vendors.')->group(function (): void {
        Route::get('/', [VendorController::class, 'index'])
            ->middleware('application-ability:vendor.view')
            ->name('index');
        Route::get('/create', [VendorController::class, 'create'])
            ->middleware('application-ability:vendor.create')
            ->name('create');
        Route::post('/', [VendorController::class, 'store'])
            ->middleware('application-ability:vendor.create')
            ->name('store');
        Route::get('/{vendor}/edit', [VendorController::class, 'edit'])
            ->middleware('application-ability:vendor.update')
            ->whereNumber('vendor')
            ->name('edit');
        Route::put('/{vendor}', [VendorController::class, 'update'])
            ->middleware('application-ability:vendor.update')
            ->whereNumber('vendor')
            ->name('update');
        Route::post('/{vendor}/deactivate', [VendorController::class, 'deactivate'])
            ->middleware('application-ability:vendor.deactivate')
            ->whereNumber('vendor')
            ->name('deactivate');
        Route::post('/{vendor}/reactivate', [VendorController::class, 'reactivate'])
            ->middleware('application-ability:vendor.reactivate')
            ->whereNumber('vendor')
            ->name('reactivate');
        Route::delete('/{vendor}', [VendorController::class, 'destroy'])
            ->middleware('application-ability:vendor.delete')
            ->whereNumber('vendor')
            ->name('destroy');
        Route::get('/{vendor}/history', [VendorController::class, 'history'])
            ->middleware('application-ability:vendor.view-revisions')
            ->whereNumber('vendor')
            ->name('history');
        Route::post('/{vendor}/history/{version}/restore', [VendorController::class, 'restore'])
            ->middleware('application-ability:vendor.restore-revision')
            ->whereNumber(['vendor', 'version'])
            ->name('history.restore');
    });

    Route::prefix('/cost-centers')->name('cost-centers.')->group(function (): void {
        Route::get('/', [CostCenterController::class, 'index'])
            ->middleware('application-ability:cost-center.view')
            ->name('index');
        Route::get('/create', [CostCenterController::class, 'create'])
            ->middleware('application-ability:cost-center.create')
            ->name('create');
        Route::post('/', [CostCenterController::class, 'store'])
            ->middleware('application-ability:cost-center.create')
            ->name('store');
        Route::get('/{costCenter}/edit', [CostCenterController::class, 'edit'])
            ->middleware('application-ability:cost-center.update')
            ->whereNumber('costCenter')
            ->name('edit');
        Route::put('/{costCenter}', [CostCenterController::class, 'update'])
            ->middleware('application-ability:cost-center.update')
            ->whereNumber('costCenter')
            ->name('update');
        Route::post('/{costCenter}/deactivate', [CostCenterController::class, 'deactivate'])
            ->middleware('application-ability:cost-center.deactivate')
            ->whereNumber('costCenter')
            ->name('deactivate');
        Route::post('/{costCenter}/reactivate', [CostCenterController::class, 'reactivate'])
            ->middleware('application-ability:cost-center.reactivate')
            ->whereNumber('costCenter')
            ->name('reactivate');
        Route::delete('/{costCenter}', [CostCenterController::class, 'destroy'])
            ->middleware('application-ability:cost-center.delete')
            ->whereNumber('costCenter')
            ->name('destroy');
        Route::get('/{costCenter}/history', [CostCenterController::class, 'history'])
            ->middleware('application-ability:cost-center.view-revisions')
            ->whereNumber('costCenter')
            ->name('history');
        Route::post('/{costCenter}/history/{version}/restore', [CostCenterController::class, 'restore'])
            ->middleware('application-ability:cost-center.restore-revision')
            ->whereNumber(['costCenter', 'version'])
            ->name('history.restore');
    });

    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('application-ability:expense.view')
        ->name('expenses.index');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])
        ->middleware('application-ability:expense.view')
        ->whereNumber('expense')
        ->name('expenses.show');
});
