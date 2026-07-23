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
use App\Http\Controllers\Api\V1\Auth\Social\SocialCallbackController;
use App\Http\Controllers\Api\V1\Auth\Social\SocialRedirectController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\ConfirmTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\DisableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\EnableTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\RecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\RegenerateRecoveryCodesController;
use App\Http\Controllers\Api\V1\Auth\TwoFactor\VerifyTwoFactorController;
use App\Http\Controllers\Api\V1\Auth\User\DeleteUserController;
use App\Http\Controllers\Api\V1\Auth\User\ShowUserController;
use App\Http\Controllers\Api\V1\Auth\User\UpdateUserController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\Workflows\IndexWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->as('v1.')->group(function (): void {

    // Public — protected by the `signed` middleware, not auth:api.
    Route::get('auth/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:auth'])
        ->name('auth.verify-email');

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
            Route::get('{provider}/redirect', SocialRedirectController::class)->name('redirect');
            Route::match(['GET', 'POST'], '{provider}/callback', SocialCallbackController::class)->name('callback');
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
            Route::get('recovery-codes', RecoveryCodesController::class)->name('recovery-codes');
            Route::post('recovery-codes/regenerate', RegenerateRecoveryCodesController::class)->name('recovery-codes.regenerate');
        });

        Route::get('sessions', IndexSessionController::class)->name('sessions.index');
        Route::delete('sessions/{id}', RevokeSessionController::class)->name('sessions.destroy');
    });

    Route::middleware(['auth:api', 'verified'])->group(function (): void {
        Route::get('workflows', IndexWorkflowController::class)->name('workflows.index');
    });
});
