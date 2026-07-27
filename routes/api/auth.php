<?php

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResendVerificationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\Session\IndexSessionController;
use App\Http\Controllers\Api\V1\Auth\Session\RevokeSessionController;
use App\Http\Controllers\Api\V1\Auth\Social\HandleSocialCallbackController;
use App\Http\Controllers\Api\V1\Auth\Social\RedirectToSocialProviderController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\DisableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\EnableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\RegenerateRecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\ShowRecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\VerifyTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\User\DeleteUserController;
use App\Http\Controllers\Api\V1\Auth\User\ShowUserController;
use App\Http\Controllers\Api\V1\Auth\User\UpdateUserController;
use Illuminate\Support\Facades\Route;

// Guest — unauthenticated auth actions.
Route::prefix('auth')->as('auth.')->middleware('throttle:auth')->group(function (): void {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', LoginController::class)->name('login');
    Route::post('refresh', RefreshTokenController::class)->name('refresh');

    Route::prefix('password')->as('password.')->group(function (): void {
        Route::post('forgot', ForgotPasswordController::class)->name('forgot');
        Route::post('reset', ResetPasswordController::class)->name('reset');
    });

    Route::prefix('2fa')->as('2fa.')->group(function (): void {
        Route::post('verify', VerifyTwoFactorController::class)->name('verify');
    });

    Route::prefix('social')->as('social.')->group(function (): void {
        Route::get('{provider}/redirect', RedirectToSocialProviderController::class)->name('redirect');
        Route::match(['GET', 'POST'], '{provider}/callback', HandleSocialCallbackController::class)->name('callback');
    });
});

// Authenticated.
Route::middleware('auth:api')->prefix('auth')->as('auth.')->group(function (): void {
    Route::post('logout', LogoutController::class)->name('logout');
    Route::post('logout-all', LogoutAllController::class)->name('logout-all');
    Route::post('verify-email/resend', ResendVerificationController::class)->name('verify-email.resend');
    Route::put('password/change', ChangePasswordController::class)->name('password.change');

    Route::get('user', ShowUserController::class)->name('user.show');
    Route::put('user', UpdateUserController::class)->name('user.update');
    Route::delete('user', DeleteUserController::class)->name('user.destroy');

    Route::prefix('2fa')->as('2fa.')->group(function (): void {
        Route::post('enable', EnableTwoFactorController::class)->name('enable');
        Route::post('confirm', ConfirmTwoFactorController::class)->name('confirm');
        Route::post('disable', DisableTwoFactorController::class)->name('disable');
        Route::get('recovery-codes', ShowRecoveryCodesController::class)->name('recovery-codes');
        Route::post('recovery-codes/regenerate', RegenerateRecoveryCodesController::class)->name('recovery-codes.regenerate');
    });

    Route::get('sessions', IndexSessionController::class)->name('sessions.index');
    Route::delete('sessions/{id}', RevokeSessionController::class)->name('sessions.destroy');
});
