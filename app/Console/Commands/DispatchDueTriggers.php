<?php

namespace App\Console\Commands;

use App\Models\Trigger;
use App\Services\TriggerFiringService;
use Cron\CronExpression;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('triggers:dispatch-due')]
#[Description('Start runs for schedule triggers whose cron expression is due')]
class DispatchDueTriggers extends Command
{
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

                // isDue() only checks "is this exact minute a match" — missed
                // windows during downtime are not backfilled, by design.
                if (! (new CronExpression($cron))->isDue(now())) {
                    return;
                }

                // Serializes overlapping command invocations for this trigger within
                // the same cron minute; TTL sits just under a minute so a stuck lock
                // self-expires before the next tick instead of wedging the trigger.
                $lock = Cache::lock("trigger:{$trigger->id}:dispatch", 55);

                if (! $lock->get()) {
                    return;
                }

                try {
                    // Re-read after acquiring the lock: a separate (non-overlapping)
                    // invocation of this command may have already fired this trigger
                    // earlier in the same cron minute — isDue() alone can't tell.
                    $trigger->refresh();

                    if ($trigger->last_run_at !== null && $trigger->last_run_at->diffInSeconds(now(), true) < 60) {
                        return;
                    }

                    if ($firing->hasInFlightRun($trigger)) {
                        return;
                    }

                    if ($firing->fire($trigger, ['scheduled_at' => now()->toIso8601String()], 'schedule') !== null) {
                        $dispatched++;
                    }
                } catch (Throwable $e) {
                    report($e);
                } finally {
                    $lock->release();
                }
            });

        $this->info("Dispatched {$dispatched} scheduled trigger(s).");

        return self::SUCCESS;
    }
}
