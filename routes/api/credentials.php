<?php

use App\Http\Controllers\Api\V1\Credentials\CredentialController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/credentials')->as('credentials.')->group(function (): void {
    Route::get('/', [CredentialController::class, 'index'])->name('index');
    Route::post('/', [CredentialController::class, 'store'])->name('store');
    Route::get('{credential}', [CredentialController::class, 'show'])->name('show');
    Route::put('{credential}', [CredentialController::class, 'update'])->name('update');
    Route::delete('{credential}', [CredentialController::class, 'destroy'])->name('destroy');
});
