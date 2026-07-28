<?php

use App\Http\Controllers\Api\V1\Runs\ConnectorMetricController;
use App\Http\Controllers\Api\V1\Runs\RunApprovalController;
use App\Http\Controllers\Api\V1\Runs\RunController;
use App\Http\Controllers\Api\V1\Runs\RunFixSuggestionController;
use App\Http\Controllers\Api\V1\Runs\RunLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/connector-metrics')->as('connector-metrics.')->group(function (): void {
    Route::get('/', [ConnectorMetricController::class, 'index'])->name('index');
    Route::get('summary', [ConnectorMetricController::class, 'summary'])->name('summary');
});

Route::prefix('{workspace}/runs')->as('runs.')->group(function (): void {
    Route::get('/', [RunController::class, 'index'])->name('index');
    Route::get('{run}', [RunController::class, 'show'])->name('show');
    Route::post('{run}/cancel', [RunController::class, 'cancel'])->name('cancel');
    Route::get('{run}/logs', [RunLogController::class, 'index'])->name('logs.index');

    Route::prefix('{run}/fix-suggestions')->as('fix-suggestions.')->group(function (): void {
        Route::get('/', [RunFixSuggestionController::class, 'index'])->name('index');
        Route::post('diagnose', [RunFixSuggestionController::class, 'diagnose'])->name('diagnose');
        Route::post('{fixSuggestion}/apply', [RunFixSuggestionController::class, 'apply'])->name('apply');
        Route::post('{fixSuggestion}/dismiss', [RunFixSuggestionController::class, 'dismiss'])->name('dismiss');
    });
    Route::post('{run}/steps/{step}/approve', [RunApprovalController::class, 'approve'])->name('steps.approve');
    Route::post('{run}/steps/{step}/reject', [RunApprovalController::class, 'reject'])->name('steps.reject');
});
