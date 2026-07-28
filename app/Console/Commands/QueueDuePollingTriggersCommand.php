<?php

namespace App\Console\Commands;

use App\Jobs\Triggers\PollTrigger;
use App\Models\Triggers\Trigger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('triggers:queue-due-polling')]
#[Description('Dispatch polling jobs for polling triggers whose interval has elapsed')]
class QueueDuePollingTriggersCommand extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        Trigger::query()
            ->where('type', 'polling')
            ->where('is_active', true)
            ->with('triggerType')
            ->each(function (Trigger $trigger) use (&$dispatched): void {
                $intervalMinutes = $trigger->triggerType?->preset_config['poll_interval_minutes']
                    ?? config('triggers.default_poll_interval_minutes');

                if ($trigger->last_run_at !== null && $trigger->last_run_at->addMinutes($intervalMinutes)->isFuture()) {
                    return;
                }

                PollTrigger::dispatch($trigger->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} polling trigger job(s).");

        return self::SUCCESS;
    }
}
