<?php

use App\Http\Controllers\Api\V1\Nodes\NodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/nodes')->as('nodes.')->group(function (): void {
    Route::get('/', [NodeController::class, 'index'])->name('index');
    Route::post('/', [NodeController::class, 'store'])->name('store');
    Route::get('{node}', [NodeController::class, 'show'])->name('show');
    Route::put('{node}', [NodeController::class, 'update'])->name('update');
    Route::delete('{node}', [NodeController::class, 'destroy'])->name('destroy');
});
