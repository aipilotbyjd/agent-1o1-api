<?php

namespace App\Services\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Workspaces\Workspace;
use Laravel\Cashier\Checkout;

class SubscriptionService
{
    private const SUBSCRIPTION_TYPE = 'default';

    public function checkout(Workspace $workspace, Plan $plan, BillingInterval $interval): string
    {
        $priceId = $plan->stripePriceId($interval);

        abort_if($priceId === null, 422, "Plan [{$plan->slug}] has no Stripe price configured for [{$interval->value}].");

        $builder = $workspace->newSubscription(self::SUBSCRIPTION_TYPE, $priceId);

        if ($plan->trial_days > 0 && $workspace->subscription(self::SUBSCRIPTION_TYPE) === null) {
            $builder = $builder->trialDays($plan->trial_days);
        }

        /** @var Checkout $checkout */
        $checkout = $builder->checkout([
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
        ]);

        return $checkout->asStripeCheckoutSession()->url;
    }

    public function swap(Workspace $workspace, Plan $plan, BillingInterval $interval): Subscription
    {
        $priceId = $plan->stripePriceId($interval);

        abort_if($priceId === null, 422, "Plan [{$plan->slug}] has no Stripe price configured for [{$interval->value}].");

        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        abort_if($subscription === null, 404, 'Workspace has no active subscription to swap.');

        $subscription->swap($priceId);
        $subscription->update(['plan_id' => $plan->id]);

        return $subscription;
    }

    public function cancel(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        abort_if($subscription === null, 404, 'Workspace has no active subscription to cancel.');

        $subscription->cancel();

        return $subscription;
    }

    public function resume(Workspace $workspace): Subscription
    {
        $subscription = $workspace->subscription(self::SUBSCRIPTION_TYPE);

        abort_if($subscription === null, 404, 'Workspace has no subscription to resume.');
        abort_unless($subscription->onGracePeriod(), 422, 'Subscription is not on its grace period.');

        $subscription->resume();

        return $subscription;
    }

    public function portalUrl(Workspace $workspace): string
    {
        return $workspace->billingPortalUrl($this->portalReturnUrl($workspace));
    }

    private function successUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=success";
    }

    private function cancelUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?checkout=cancel";
    }

    private function portalReturnUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing";
    }
}
