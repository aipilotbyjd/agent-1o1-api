<?php

use App\Http\Controllers\Api\V1\Variables\VariableController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/variables')->as('variables.')->group(function (): void {
    Route::get('/', [VariableController::class, 'index'])->name('index');
    Route::post('/', [VariableController::class, 'store'])->name('store');
    Route::put('{variable}', [VariableController::class, 'update'])->name('update');
    Route::delete('{variable}', [VariableController::class, 'destroy'])->name('destroy');
});
