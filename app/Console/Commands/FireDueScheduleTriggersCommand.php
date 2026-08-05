<?php

namespace App\Console\Commands;

use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerIntake;
use Cron\CronExpression;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Queues an event for every schedule trigger whose cron matches this minute.
 *
 * This command starts no runs. It writes a row per due trigger and returns, so
 * the every-minute tick costs the same whether one trigger is due or a thousand —
 * previously a single slow agent could consume the whole minute and cause every
 * other due trigger to be skipped.
 *
 * Double-firing is prevented by the delivery id rather than by locking: the id is
 * derived from the minute the trigger is due for, so a second invocation in the
 * same minute collides on the unique index and is recorded as a duplicate. That
 * holds across concurrent servers, which a per-process check could not.
 */
#[Signature('triggers:fire-due-schedule')]
#[Description('Queue events for schedule triggers whose cron expression is due')]
class FireDueScheduleTriggersCommand extends Command
{
    public function handle(TriggerIntake $intake): int
    {
        $minute = now()->format('Y-m-d H:i');
        $queued = 0;

        Trigger::query()
            ->where('type', 'schedule')
            ->where('is_active', true)
            ->with('triggerable')
            ->each(function (Trigger $trigger) use ($intake, $minute, &$queued): void {
                $cron = $trigger->config['cron'] ?? null;

                if ($cron === null || ! CronExpression::isValidExpression($cron)) {
                    return;
                }

                // isDue() only checks "is this exact minute a match" — missed
                // windows during downtime are not backfilled, by design.
                if (! (new CronExpression($cron))->isDue(now())) {
                    return;
                }

                $result = $intake->accept(
                    $trigger,
                    'schedule',
                    ['scheduled_at' => now()->toIso8601String()],
                    deliveryId: "schedule:{$minute}",
                );

                if ($result->isQueued()) {
                    $queued++;
                }
            });

        $this->info("Queued {$queued} scheduled trigger event(s).");

        return self::SUCCESS;
    }
}
