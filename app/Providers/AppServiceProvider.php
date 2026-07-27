<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureAuthNotificationUrls();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
    }

    private function configurePassport(): void
    {
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
        Passport::enablePasswordGrant();
    }

    private function configureAuthNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(fn (object $notifiable): string => URL::temporarySignedRoute(
            'v1.auth.verify-email',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        ));

        ResetPassword::createUrlUsing(fn (object $notifiable, string $token): string => rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));
    }

    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers()->symbols());
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('trigger-hooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('triggers.hook_rate_limit_per_minute'),
        )->by($request->route('token') ?? $request->ip()));
    }
}
