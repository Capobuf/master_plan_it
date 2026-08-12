<?php

use App\Http\Controllers\Api\V1\ExpenseAttachmentController;
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
        Route::post('/preview', [ExpenseController::class, 'preview'])
            ->name('preview');
        Route::put('/register-preferences', [ExpenseController::class, 'updateRegisterPreferences'])
            ->middleware('application-ability:expense.view')
            ->name('register-preferences.update');
        Route::post('/bulk-actions', [ExpenseController::class, 'bulk'])
            ->name('bulk');
        Route::get('/{expense}', [ExpenseController::class, 'show'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.view')
            ->name('show');
        Route::get('/{expense}/history', [ExpenseController::class, 'history'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.view-revisions')
            ->name('history');
        Route::get('/{expense}/history/{revision}', [ExpenseController::class, 'revision'])
            ->whereNumber(['expense', 'revision'])
            ->middleware('application-ability:expense.view-revisions')
            ->name('history.show');
        Route::post('/{expense}/history/{revision}/restore', [ExpenseController::class, 'restore'])
            ->whereNumber(['expense', 'revision'])
            ->middleware('application-ability:expense.restore-revision')
            ->name('history.restore');
        Route::get('/{expense}/attachments', [ExpenseAttachmentController::class, 'index'])
            ->whereNumber('expense')->middleware(['application-ability:expense.view', 'application-ability:attachment.view'])->name('attachments.index');
        Route::post('/{expense}/attachments', [ExpenseAttachmentController::class, 'store'])
            ->whereNumber('expense')->middleware(['application-ability:expense.update', 'application-ability:attachment.upload'])->name('attachments.store');
        Route::get('/{expense}/attachments/{attachment}/download', [ExpenseAttachmentController::class, 'download'])
            ->whereNumber(['expense', 'attachment'])->middleware(['application-ability:expense.view', 'application-ability:attachment.view'])->name('attachments.download');
        Route::delete('/{expense}/attachments/{attachment}', [ExpenseAttachmentController::class, 'destroy'])
            ->whereNumber(['expense', 'attachment'])->middleware(['application-ability:expense.delete', 'application-ability:attachment.delete'])->name('attachments.destroy');
        Route::get('/{expense}/rows/{row}/attachments', [ExpenseAttachmentController::class, 'rowIndex'])
            ->whereNumber(['expense', 'row'])->middleware(['application-ability:expense.view', 'application-ability:attachment.view'])->name('rows.attachments.index');
        Route::post('/{expense}/rows/{row}/attachments', [ExpenseAttachmentController::class, 'rowStore'])
            ->whereNumber(['expense', 'row'])->middleware(['application-ability:expense.update', 'application-ability:attachment.upload'])->name('rows.attachments.store');
        Route::get('/{expense}/rows/{row}/attachments/{attachment}/download', [ExpenseAttachmentController::class, 'rowDownload'])
            ->whereNumber(['expense', 'row', 'attachment'])->middleware(['application-ability:expense.view', 'application-ability:attachment.view'])->name('rows.attachments.download');
        Route::delete('/{expense}/rows/{row}/attachments/{attachment}', [ExpenseAttachmentController::class, 'rowDestroy'])
            ->whereNumber(['expense', 'row', 'attachment'])->middleware(['application-ability:expense.delete', 'application-ability:attachment.delete'])->name('rows.attachments.destroy');
        Route::put('/{expense}', [ExpenseController::class, 'update'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.update')
            ->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
            ->whereNumber('expense')
            ->middleware('application-ability:expense.delete')
            ->name('destroy');
    });
