<?php

namespace App\Console\Commands;

use App\Models\Trigger;
use App\Services\TriggerFiringService;
use Cron\CronExpression;
use Illuminate\Console\Command;

class DispatchDueTriggers extends Command
{
    protected $signature = 'triggers:dispatch-due';

    protected $description = 'Start runs for schedule triggers whose cron expression is due';

    public function handle(TriggerFiringService $firing): int
    {
        $dispatched = 0;

        Trigger::query()
            ->where('type', 'schedule')
            ->where('is_active', true)
            ->with('triggerable')
            ->each(function (Trigger $trigger) use ($firing, &$dispatched): void {
                $cron = $trigger->config['cron'] ?? null;

                if ($cron === null || ! CronExpression::isValidExpression($cron)) {
                    return;
                }

                if (! (new CronExpression($cron))->isDue(now())) {
                    return;
                }

                // Guard against double dispatch when the command overlaps within a minute.
                if ($trigger->last_run_at !== null && $trigger->last_run_at->diffInSeconds(now(), true) < 60) {
                    return;
                }

                if ($firing->fire($trigger, ['scheduled_at' => now()->toIso8601String()], 'schedule') !== null) {
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} scheduled trigger(s).");

        return self::SUCCESS;
    }
}
