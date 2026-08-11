<?php

use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Middleware\RejectBearerTokens;
use Illuminate\Support\Facades\Route;

Route::middleware(RejectBearerTokens::class)
    ->middleware(['auth:sanctum', 'active-user', 'tenant-context', 'permission-team-context', 'active-tenant'])
    ->prefix('contracts')->name('api.v1.contracts.')
    ->group(function (): void {
        Route::get('/', [ContractController::class, 'index'])
            ->middleware('application-ability:contract.view')->name('index');
        Route::post('/', [ContractController::class, 'store'])
            ->middleware('application-ability:contract.create')->name('store');
        Route::get('/{contract}', [ContractController::class, 'show'])
            ->middleware('application-ability:contract.view')->whereNumber('contract')->name('show');
        Route::put('/{contract}', [ContractController::class, 'update'])
            ->middleware('application-ability:contract.update')->whereNumber('contract')->name('update');
        Route::delete('/{contract}', [ContractController::class, 'destroy'])
            ->middleware('application-ability:contract.delete')->whereNumber('contract')->name('destroy');
        Route::get('/{contract}/history', [ContractController::class, 'history'])
            ->middleware('application-ability:contract.view-revisions')->whereNumber('contract')->name('history');
        Route::post('/{contract}/synchronize', [ContractController::class, 'synchronize'])
            ->middleware('application-ability:contract.generate-occurrence')->whereNumber('contract')->name('synchronize');
        Route::post('/{contract}/generate/{year}', [ContractController::class, 'generate'])
            ->middleware('application-ability:contract.generate-occurrence')->whereNumber(['contract', 'year'])->name('generate');
        Route::post('/{contract}/occurrences/{sourceKey}/suppress', [ContractController::class, 'suppress'])
            ->middleware('application-ability:contract.suppress-generation')->whereNumber('contract')->where('sourceKey', '[A-Fa-f0-9]{64}')->name('occurrences.suppress');
        Route::post('/{contract}/occurrences/{sourceKey}/resume', [ContractController::class, 'resume'])
            ->middleware('application-ability:contract.resume-generation')->whereNumber('contract')->where('sourceKey', '[A-Fa-f0-9]{64}')->name('occurrences.resume');
        Route::post('/{contract}/occurrences/{sourceKey}/resume-and-generate', [ContractController::class, 'resumeAndGenerate'])
            ->middleware('application-ability:contract.resume-generation')->whereNumber('contract')->where('sourceKey', '[A-Fa-f0-9]{64}')->name('occurrences.resume-and-generate');
        Route::delete('/{contract}/terms/{term}', [ContractController::class, 'destroyTerm'])
            ->middleware('application-ability:contract.update')->whereNumber(['contract', 'term'])->name('terms.destroy');
        Route::delete('/{contract}/generated-expenses/{expense}', [ContractController::class, 'destroyGeneratedExpense'])
            ->middleware('application-ability:expense.delete')->whereNumber(['contract', 'expense'])->name('generated-expenses.destroy');
    });
