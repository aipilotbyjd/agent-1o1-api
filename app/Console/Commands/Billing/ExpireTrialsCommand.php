<?php

namespace App\Console\Commands\Billing;

use App\Models\Billing\Subscription;
use App\Notifications\Billing\TrialEndingNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Stripe is the source of truth for when a trial actually ends — it flips the
 * subscription's status via webhook and (if a payment method is attached) charges
 * automatically. This command never mutates subscription state; it only reminds
 * workspaces still on trial that it's ending soon.
 */
#[Signature('billing:notify-trial-ending')]
#[Description('Notifies workspaces whose trial ends within 3 days')]
class ExpireTrialsCommand extends Command
{
    private const REMINDER_WINDOW_DAYS = 3;

    public function handle(): int
    {
        $notified = 0;

        Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays(self::REMINDER_WINDOW_DAYS))
            ->with('workspace.owner')
            ->each(function (Subscription $subscription) use (&$notified): void {
                if (! $subscription->onTrial()) {
                    return;
                }

                // One reminder per subscription per day.
                $lock = Cache::lock("billing:trial-reminder:{$subscription->id}:".now()->toDateString(), 3600);

                if (! $lock->get()) {
                    return;
                }

                $daysRemaining = max(1, now()->diffInDays($subscription->trial_ends_at, false));

                $subscription->workspace->owner->notify(
                    new TrialEndingNotification($subscription->workspace, $daysRemaining),
                );

                $notified++;
            });

        $this->info("Notified {$notified} workspace(s) about their ending trial.");

        return self::SUCCESS;
    }
}
