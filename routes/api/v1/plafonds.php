<?php

use App\Http\Controllers\Api\V1\PlafondController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->prefix('/plafonds')
    ->name('api.v1.plafonds.')
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->group(function (): void {
        Route::get('/', [PlafondController::class, 'index'])
            ->middleware('application-ability:expense.view')
            ->name('index');
        Route::post('/', [PlafondController::class, 'store'])
            ->middleware('application-ability:expense.create')
            ->name('store');
        Route::get('/report', [PlafondController::class, 'report'])
            ->middleware('application-ability:expense.view')
            ->name('report');
        Route::get('/{plafond}', [PlafondController::class, 'show'])
            ->whereNumber('plafond')
            ->middleware('application-ability:expense.view')
            ->name('show');
        Route::post('/{plafond}/allocation-adjustments/preview', [PlafondController::class, 'previewAdjustment'])
            ->whereNumber('plafond')
            ->middleware('application-ability:expense.update')
            ->name('allocation-adjustments.preview');
        Route::post('/{plafond}/allocation-adjustments', [PlafondController::class, 'addAdjustment'])
            ->whereNumber('plafond')
            ->middleware('application-ability:expense.update')
            ->name('allocation-adjustments.store');
    });
