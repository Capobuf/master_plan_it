<?php

use App\Livewire\Expenses\ExpenseIndex;
use App\Livewire\Expenses\ExpenseShow;
use App\Models\Expense;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'active-user',
    'tenant-context',
    'permission-team-context',
    'active-tenant',
    'tenant-presentation',
])->group(function (): void {
    Route::view('/operational', 'layouts.operational')
        ->middleware('can:dashboard.view')
        ->name('operational.index');

    Route::middleware('can:viewAny,'.Expense::class)
        ->prefix('/operational/expenses')
        ->name('operational.expenses.')
        ->group(function (): void {
            Route::livewire('/', ExpenseIndex::class)->name('index');
            Route::livewire('/{expense}', ExpenseShow::class)
                ->whereNumber('expense')
                ->name('show');
        });
});
