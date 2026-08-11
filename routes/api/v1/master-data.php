<?php

use App\Http\Controllers\Api\V1\CostCenterController;
use App\Http\Controllers\Api\V1\VendorController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->group(function (): void {
        Route::prefix('vendors')->name('api.v1.vendors.')->group(function (): void {
            Route::get('/', [VendorController::class, 'index'])
                ->middleware('application-ability:vendor.view')->name('index');
            Route::post('/', [VendorController::class, 'store'])
                ->middleware('application-ability:vendor.create')->name('store');
            Route::get('/{vendor}', [VendorController::class, 'show'])
                ->middleware('application-ability:vendor.view')->whereNumber('vendor')->name('show');
            Route::put('/{vendor}', [VendorController::class, 'update'])
                ->middleware('application-ability:vendor.update')->whereNumber('vendor')->name('update');
            Route::post('/{vendor}/deactivate', [VendorController::class, 'deactivate'])
                ->middleware('application-ability:vendor.deactivate')->whereNumber('vendor')->name('deactivate');
            Route::post('/{vendor}/reactivate', [VendorController::class, 'reactivate'])
                ->middleware('application-ability:vendor.reactivate')->whereNumber('vendor')->name('reactivate');
            Route::delete('/{vendor}', [VendorController::class, 'destroy'])
                ->middleware('application-ability:vendor.delete')->whereNumber('vendor')->name('destroy');
            Route::get('/{vendor}/history', [VendorController::class, 'history'])
                ->middleware('application-ability:vendor.view-revisions')->whereNumber('vendor')->name('history');
            Route::post('/{vendor}/history/{version}/restore', [VendorController::class, 'restore'])
                ->middleware('application-ability:vendor.restore-revision')
                ->whereNumber(['vendor', 'version'])->name('restore');
        });

        Route::prefix('cost-centers')->name('api.v1.cost-centers.')->group(function (): void {
            Route::get('/', [CostCenterController::class, 'index'])
                ->middleware('application-ability:cost-center.view')->name('index');
            Route::get('/tree', [CostCenterController::class, 'hierarchy'])
                ->middleware('application-ability:cost-center.view')->name('tree');
            Route::post('/', [CostCenterController::class, 'store'])
                ->middleware('application-ability:cost-center.create')->name('store');
            Route::get('/{costCenter}', [CostCenterController::class, 'show'])
                ->middleware('application-ability:cost-center.view')->whereNumber('costCenter')->name('show');
            Route::put('/{costCenter}', [CostCenterController::class, 'update'])
                ->middleware('application-ability:cost-center.update')->whereNumber('costCenter')->name('update');
            Route::post('/{costCenter}/deactivate', [CostCenterController::class, 'deactivate'])
                ->middleware('application-ability:cost-center.deactivate')->whereNumber('costCenter')->name('deactivate');
            Route::post('/{costCenter}/reactivate', [CostCenterController::class, 'reactivate'])
                ->middleware('application-ability:cost-center.reactivate')->whereNumber('costCenter')->name('reactivate');
            Route::delete('/{costCenter}', [CostCenterController::class, 'destroy'])
                ->middleware('application-ability:cost-center.delete')->whereNumber('costCenter')->name('destroy');
            Route::get('/{costCenter}/history', [CostCenterController::class, 'history'])
                ->middleware('application-ability:cost-center.view-revisions')->whereNumber('costCenter')->name('history');
            Route::post('/{costCenter}/history/{version}/restore', [CostCenterController::class, 'restore'])
                ->middleware('application-ability:cost-center.restore-revision')
                ->whereNumber(['costCenter', 'version'])->name('restore');
        });
    });
