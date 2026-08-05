<?php

namespace App\Jobs\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Services\Triggers\TriggerFiringService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Fires one already-accepted trigger event.
 *
 * Everything slow lives here: starting a workflow run, or blocking on a model
 * call for an agent. Because the event is already durably stored, this job is
 * free to fail and be retried without anything being lost.
 *
 * Attempts are bounded by time rather than by count. WithoutOverlapping releases
 * the job back onto the queue while a sibling event for the same trigger is in
 * flight, and a released job burns an attempt — so a count-based limit would
 * expire a busy trigger's events without them ever having been tried. maxExceptions
 * bounds the thing that actually matters: real failures.
 */
class ProcessTriggerEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Real failures tolerated before the event is given up on. Releases from the
     * overlap middleware are not counted against this.
     */
    public int $maxExceptions = 3;

    /**
     * Generous enough for an agent's model call to finish; the agent queue's
     * worker timeout is configured to match.
     */
    public int $timeout = 300;

    public function __construct(
        public int $eventId,
        public int $triggerId,
    ) {}

    /**
     * The window an event may keep retrying in, after which it is failed for good.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('triggers.processing.retry_window_minutes'));
    }

    /**
     * Backoff between real failures — quick first, then long enough to ride out a
     * provider outage rather than hammering it.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            // Serializes events per trigger. releaseAfter puts the event back on the
            // queue instead of discarding it, which is the difference between "runs a
            // few seconds later" and the dropped-on-conflict behaviour this replaces.
            (new WithoutOverlapping((string) $this->triggerId))
                ->releaseAfter((int) config('triggers.processing.overlap_release_seconds'))
                ->expireAfter($this->timeout * 2),
        ];
    }

    public function handle(TriggerFiringService $firing): void
    {
        $event = TriggerEvent::find($this->eventId);
        $trigger = Trigger::with('triggerable')->find($this->triggerId);

        if ($event === null || $trigger === null) {
            return;
        }

        // A terminal event is already accounted for — a re-dispatch from the
        // reconciler or a duplicated job must not start a second run.
        if (! $event->claim()) {
            return;
        }

        if (! $trigger->is_active) {
            $event->markResolved(TriggerEventStatus::Skipped, 'Trigger is inactive');

            return;
        }

        if (! $firing->isRunnable($trigger)) {
            $event->markResolved(TriggerEventStatus::Skipped, 'Target not runnable');

            return;
        }

        // Thrown exceptions leave the event in `processing` on purpose: the retry
        // re-claims it, and if every retry is exhausted failed() settles it.
        $run = $firing->fire($trigger, $event->payload ?? [], $event->source);

        if ($run === null) {
            $event->markResolved(TriggerEventStatus::Skipped, 'Target not runnable');

            return;
        }

        $event->markMatched($run);
    }

    /**
     * Only reached once the retry window or exception budget is spent, which is
     * why the trigger's failure streak is counted here rather than per attempt —
     * an event that succeeds on its second try is not a failure.
     */
    public function failed(?Throwable $exception): void
    {
        $event = TriggerEvent::find($this->eventId);

        if ($event === null || $event->status->isTerminal()) {
            return;
        }

        $event->markResolved(
            TriggerEventStatus::Failed,
            $exception?->getMessage() ?? 'Processing failed.',
        );

        // An event that expired without ever being claimed never reached the
        // target, so it says nothing about the target's health — that is queue
        // contention, and counting it would let backlog alone trip the breaker.
        if ($event->attempts > 0) {
            Trigger::find($this->triggerId)?->registerFailure();
        }
    }
}
