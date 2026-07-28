<?php

use App\Http\Controllers\Api\V1\Billing\BillingController;
use App\Http\Controllers\Api\V1\Billing\CreditController;
use App\Http\Controllers\Api\V1\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('{workspace}/billing')->as('billing.')->group(function (): void {
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
    Route::get('subscription/portal', [SubscriptionController::class, 'portal'])->name('subscription.portal');

    Route::get('packs', [BillingController::class, 'packCatalog'])->name('packs.index');
    Route::post('packs/checkout', [BillingController::class, 'packCheckout'])->name('packs.checkout');

    Route::get('credits', [CreditController::class, 'show'])->name('credits.show');
});
