<?php

use App\Http\Controllers\Api\V1\Workflows\WorkflowBuilderMessageController;
use App\Http\Controllers\Api\V1\Workflows\WorkflowBuilderSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/workflow-builder-sessions')->as('workflow-builder-sessions.')->group(function (): void {
    Route::get('/', [WorkflowBuilderSessionController::class, 'index'])->name('index');
    Route::post('/', [WorkflowBuilderSessionController::class, 'store'])->name('store');
    Route::get('{session}', [WorkflowBuilderSessionController::class, 'show'])->name('show');
    Route::delete('{session}', [WorkflowBuilderSessionController::class, 'destroy'])->name('destroy');

    Route::prefix('{session}/messages')->as('messages.')->group(function (): void {
        Route::get('/', [WorkflowBuilderMessageController::class, 'index'])->name('index');
        Route::post('/', [WorkflowBuilderMessageController::class, 'store'])->name('store');
    });
});
