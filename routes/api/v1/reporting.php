<?php

use App\Http\Controllers\Api\V1\CurrentBudgetController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EconomicReportController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->group(function (): void {
        Route::get('/dashboard', DashboardController::class)
            ->middleware('application-ability:dashboard.view')
            ->name('api.v1.dashboard');
        Route::get('/budget', CurrentBudgetController::class)
            ->middleware('application-ability:budget.view')
            ->name('api.v1.budget');
        Route::get('/reports', EconomicReportController::class)
            ->middleware('application-ability:report.view')
            ->name('api.v1.reports');
    });
