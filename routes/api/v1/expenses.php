<?php

use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->prefix('/expenses')
    ->name('api.v1.expenses.')
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->group(function (): void {
        Route::get('/', [ExpenseController::class, 'index'])
            ->middleware('application-ability:expense.view')
            ->name('index');
        Route::post('/', [ExpenseController::class, 'store'])
            ->middleware('application-ability:expense.create')
            ->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.view')
            ->name('show');
        Route::put('/{expense}', [ExpenseController::class, 'update'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.update')
            ->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.delete')
            ->name('destroy');
        Route::post('/{expense}/rows/{row}/confirm', [ExpenseController::class, 'confirm'])
            ->whereNumber('expense')
            ->whereNumber('row')
            ->middleware('application-ability:expense.confirm-actual')
            ->name('confirm');
    });
