<?php

namespace App\Providers;

use App\Authorization\WorkspaceContext;
use App\Enums\Workspaces\Permission;
use App\Models\Agents\Agent;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Subscription as BillingSubscription;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Observers\WorkspaceMemberObserver;
use App\Services\Runs\SecretRedactor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Memoizes decrypted secrets per workspace, so a run that logs many lines
        // decrypts each credential once instead of once per line.
        $this->app->singleton(SecretRedactor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->configurePassport();
        $this->configureAuthNotificationUrls();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
        $this->configureGate();
        $this->configureObservers();
        $this->configureCashier();
    }

    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'agent' => Agent::class,
            'credit_pack' => CreditPack::class,
            'run' => Run::class,
            'trigger' => Trigger::class,
            'user' => User::class,
            'workflow' => Workflow::class,
        ]);
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

    private function configureGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            $permission = Permission::tryFrom($ability);

            if ($permission === null) {
                return null;
            }

            if (! app()->bound(WorkspaceContext::class)) {
                return false;
            }

            return app(WorkspaceContext::class)->allows($permission) ?: null;
        });
    }

    private function configureObservers(): void
    {
        WorkspaceMember::observe(WorkspaceMemberObserver::class);
    }

    private function configureCashier(): void
    {
        Cashier::useCustomerModel(Workspace::class);
        Cashier::useSubscriptionModel(BillingSubscription::class);

        // Auto-registration is disabled so the webhook route resolves to our
        // StripeWebhookController (idempotency guard + credit pack fulfillment)
        // instead of Cashier's own controller. Re-registered in routes/web.php.
        Cashier::ignoreRoutes();
    }
}
