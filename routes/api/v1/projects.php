<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->prefix('projects')->name('api.v1.projects.')
    ->group(function (): void {
        Route::get('/', [ProjectController::class, 'index'])
            ->middleware('application-ability:project.view')->name('index');
        Route::post('/', [ProjectController::class, 'store'])
            ->middleware('application-ability:project.create')->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])
            ->middleware('application-ability:project.view')->whereNumber('project')->name('show');
        Route::put('/{project}', [ProjectController::class, 'update'])
            ->middleware('application-ability:project.update')->whereNumber('project')->name('update');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])
            ->middleware('application-ability:project.delete')->whereNumber('project')->name('destroy');
        Route::get('/{project}/history', [ProjectController::class, 'history'])
            ->middleware('application-ability:project.view-revisions')->whereNumber('project')->name('history');
        Route::get('/{project}/history/{revision}', [ProjectController::class, 'revision'])
            ->middleware('application-ability:project.view-revisions')->whereNumber(['project', 'revision'])->name('history.show');
        Route::post('/{project}/history/{revision}/restore', [ProjectController::class, 'restore'])
            ->middleware('application-ability:project.restore-revision')->whereNumber(['project', 'revision'])->name('history.restore');
    });
