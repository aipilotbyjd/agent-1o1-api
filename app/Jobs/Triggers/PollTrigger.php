<?php

namespace App\Jobs\Triggers;

use App\Models\Credentials\Credential;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerIntake;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Fetches a polling trigger's source and queues an event per new item.
 *
 * This job only reads and enqueues — firing happens in {@see ProcessTriggerEvent}.
 * Keeping the work out of here is what lets the timeout stay short: the job's
 * runtime is now bounded by one HTTP call rather than by however long it takes to
 * run every item it found.
 */
class PollTrigger implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    // Slightly over $timeout: a slow poll can still be mid-flight when the next
    // triggers:queue-due-polling tick fires — this keeps a second job for the same
    // trigger from queuing (and racing on poll_cursor) until this one finishes.
    public int $uniqueFor = 120;

    public function __construct(public int $triggerId) {}

    public function uniqueId(): string
    {
        return (string) $this->triggerId;
    }

    public function handle(TriggerIntake $intake): void
    {
        $trigger = Trigger::with(['triggerType', 'credential', 'triggerable'])->find($this->triggerId);

        if ($trigger === null || ! $trigger->is_active) {
            return;
        }

        $preset = $trigger->triggerType?->preset_config ?? [];
        $pollUrl = $preset['poll_url'] ?? null;
        $itemsPath = $preset['items_path'] ?? null;
        $cursorPath = $preset['cursor_path'] ?? null;

        if ($pollUrl === null || $itemsPath === null || $cursorPath === null) {
            return;
        }

        try {
            $response = $this->applyCredential(Http::timeout(15), $trigger)->get($pollUrl);
            $response->throw();
        } catch (Throwable $e) {
            // The poll itself failing is a trigger-level fault (bad URL, dead
            // credential), unlike a single item failing to run — so it counts
            // against the circuit breaker here.
            $trigger->registerFailure();
            $intake->recordFailure($trigger, 'poll', $e->getMessage());
            report($e);

            return;
        }

        $items = data_get($response->json(), $itemsPath, []);
        $lastCursor = $trigger->poll_cursor['value'] ?? null;
        $maxCursor = $lastCursor;

        foreach ($items as $item) {
            $cursorValue = data_get($item, $cursorPath);

            if (! $this->isNewerThan($cursorValue, $lastCursor)) {
                continue;
            }

            // The item's own cursor is the idempotency key. If this job dies after
            // queuing some items but before the cursor reaches them, the next poll
            // re-offers those items and the unique index rejects them — so a
            // crash mid-loop costs nothing and duplicates nothing.
            $intake->accept(
                $trigger,
                'poll',
                is_array($item) ? $item : ['value' => $item],
                deliveryId: 'poll:'.$cursorValue,
            );

            if ($this->isNewerThan($cursorValue, $maxCursor)) {
                $maxCursor = $cursorValue;

                // Advanced per item rather than after the loop: the events are
                // already durable, so there is no reason to risk re-offering them.
                $trigger->update(['poll_cursor' => ['value' => $maxCursor]]);
            }
        }

        // Always stamp last_run_at on a completed poll attempt (even with no new
        // items) so QueueDuePollingTriggersCommand's interval due-check advances
        // correctly — it's left untouched on a failed attempt so the next tick
        // retries sooner.
        $trigger->update(['last_run_at' => now()]);
    }

    private function isNewerThan(mixed $value, mixed $baseline): bool
    {
        if ($baseline === null) {
            return $value !== null;
        }

        if (is_numeric($value) && is_numeric($baseline)) {
            return (float) $value > (float) $baseline;
        }

        return (string) $value !== (string) $baseline;
    }

    private function applyCredential(PendingRequest $request, Trigger $trigger): PendingRequest
    {
        $credential = $trigger->credential;

        if ($credential === null) {
            return $request;
        }

        if ($credential->isExpired()) {
            throw new InvalidArgumentException("Credential [{$credential->name}] has expired.");
        }

        $credential->touchLastUsed();
        $data = $credential->data ?? [];

        return match ($credential->type) {
            Credential::TYPE_BEARER_TOKEN => $request->withToken((string) ($data['token'] ?? '')),
            Credential::TYPE_BASIC_AUTH => $request->withBasicAuth(
                (string) ($data['username'] ?? ''),
                (string) ($data['password'] ?? ''),
            ),
            Credential::TYPE_API_KEY => $request->withHeaders([
                (string) ($data['header'] ?? 'Authorization') => (string) ($data['value'] ?? ''),
            ]),
            default => $request,
        };
    }
}
