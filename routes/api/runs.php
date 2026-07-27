<?php

use App\Http\Controllers\Api\V1\Runs\RunApprovalController;
use App\Http\Controllers\Api\V1\Runs\RunController;
use App\Http\Controllers\Api\V1\Runs\RunLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/runs')->as('runs.')->group(function (): void {
    Route::get('/', [RunController::class, 'index'])->name('index');
    Route::get('{run}', [RunController::class, 'show'])->name('show');
    Route::post('{run}/cancel', [RunController::class, 'cancel'])->name('cancel');
    Route::get('{run}/logs', [RunLogController::class, 'index'])->name('logs.index');
    Route::post('{run}/steps/{step}/approve', [RunApprovalController::class, 'approve'])->name('steps.approve');
    Route::post('{run}/steps/{step}/reject', [RunApprovalController::class, 'reject'])->name('steps.reject');
});
