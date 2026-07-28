<?php

namespace App\Console\Commands\Billing;

use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('billing:rollover-credits')]
#[Description('Closes usage periods that have ended and opens the next period, rolling over unused pack credits')]
class RolloverCreditsCommand extends Command
{
    public function handle(): int
    {
        $rolledOver = 0;

        UsagePeriod::query()
            ->where('is_current', true)
            ->where('period_end', '<', now()->toDateString())
            ->with('workspace.subscription.plan')
            ->each(function (UsagePeriod $period) use (&$rolledOver): void {
                try {
                    DB::transaction(function () use ($period): void {
                        $this->rolloverPeriod($period->workspace, $period);
                    });

                    $rolledOver++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Rolled over {$rolledOver} usage period(s).");

        return self::SUCCESS;
    }

    private function rolloverPeriod(Workspace $workspace, UsagePeriod $period): void
    {
        $locked = UsagePeriod::query()->whereKey($period->id)->lockForUpdate()->first();

        if ($locked === null || ! $locked->is_current) {
            return;
        }

        $plan = $workspace->subscription('default')?->plan;
        $unusedPackCredits = max(0, $locked->credits_from_packs - max(0, $locked->credits_used - $locked->credits_limit));

        $locked->update(['is_current' => false]);

        UsagePeriod::create([
            'workspace_id' => $workspace->id,
            'subscription_id' => $locked->subscription_id,
            'period_start' => $locked->period_end->copy()->addDay(),
            'period_end' => $locked->period_end->copy()->addMonthNoOverflow(),
            'credits_limit' => $plan?->creditsMonthly() ?? 0,
            'credits_from_packs' => 0,
            'credits_rolled_over' => $unusedPackCredits,
            'credits_used' => 0,
            'executions_total' => 0,
            'is_current' => true,
        ]);
    }
}
