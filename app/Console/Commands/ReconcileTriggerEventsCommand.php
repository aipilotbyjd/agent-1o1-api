<?php

namespace App\Console\Commands;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\TriggerEvent;
use App\Services\Triggers\TriggerIntake;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-queues trigger events whose job never came back.
 *
 * Storing the event before dispatching it means the row outlives the job. What
 * this command exists for is the gap between those two steps and everything after
 * it: a dispatch that failed, a queue that was flushed, a worker killed mid-run.
 * In all of those the event is still sitting at `pending` or `processing` with no
 * job that will ever finish it.
 *
 * This is what turns "we retry failures" into "nothing is lost" — retries only
 * help while a job exists to be retried.
 */
#[Signature('triggers:reconcile')]
#[Description('Re-queue trigger events that were accepted but never processed')]
class ReconcileTriggerEventsCommand extends Command
{
    public function handle(TriggerIntake $intake): int
    {
        $pendingBefore = now()->subMinutes((int) config('triggers.reconcile.pending_after_minutes'));
        $processingBefore = now()->subMinutes((int) config('triggers.reconcile.processing_after_minutes'));
        $requeued = 0;

        TriggerEvent::query()
            ->with('trigger')
            ->whereIn('status', TriggerEventStatus::unresolved())
            // Grace periods keep this from racing events that are simply still in
            // flight; anything older than them has no live worker behind it.
            ->where(function ($query) use ($pendingBefore, $processingBefore): void {
                $query
                    ->where(fn ($q) => $q
                        ->where('status', TriggerEventStatus::Pending)
                        ->where('created_at', '<', $pendingBefore))
                    ->orWhere(fn ($q) => $q
                        ->where('status', TriggerEventStatus::Processing)
                        ->where('updated_at', '<', $processingBefore));
            })
            ->oldest()
            ->limit((int) config('triggers.reconcile.batch'))
            ->get()
            ->each(function (TriggerEvent $event) use ($intake, &$requeued): void {
                $trigger = $event->trigger;

                // The trigger was deleted or switched off while the event waited;
                // there is nothing to fire it against any more.
                if ($trigger === null || ! $trigger->is_active) {
                    $event->markResolved(TriggerEventStatus::Skipped, 'Trigger unavailable at reconcile time');

                    return;
                }

                // Back to pending so the re-dispatched job can claim it — claim()
                // only accepts events in a non-terminal state, and a stale
                // `processing` row would otherwise look identical to a live one.
                $event->update(['status' => TriggerEventStatus::Pending]);

                $intake->dispatch($trigger, $event);
                $requeued++;
            });

        $this->info("Re-queued {$requeued} stranded trigger event(s).");

        return self::SUCCESS;
    }
}
