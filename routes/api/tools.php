<?php

use App\Http\Controllers\Api\V1\Tools\ToolController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/tools')->as('tools.')->group(function (): void {
    Route::get('/', [ToolController::class, 'index'])->name('index');
    Route::post('/', [ToolController::class, 'store'])->name('store');
    Route::get('{tool}', [ToolController::class, 'show'])->name('show');
    Route::put('{tool}', [ToolController::class, 'update'])->name('update');
    Route::delete('{tool}', [ToolController::class, 'destroy'])->name('destroy');
    Route::post('{tool}/test', [ToolController::class, 'test'])->name('test');
});
