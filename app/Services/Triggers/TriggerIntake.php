<?php

namespace App\Services\Triggers;

use App\Console\Commands\ReconcileTriggerEventsCommand;
use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\ProcessTriggerEvent;
use App\Models\Agents\Agent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The single write path into trigger_events, and the only place that decides
 * whether an inbound event becomes queued work.
 *
 * Intake is deliberately cheap: a handful of indexed queries and one insert, so a
 * webhook can be acknowledged in milliseconds no matter how slow the run it
 * starts turns out to be. Everything that can block — calling a model, walking a
 * workflow graph — happens later in {@see ProcessTriggerEvent}.
 *
 * The rule that makes this lossless: the event row is committed *before* the job
 * is dispatched. If the dispatch fails, the process dies, or the queue loses the
 * job, the row is still sitting at `pending` for
 * {@see ReconcileTriggerEventsCommand} to pick back up.
 */
class TriggerIntake
{
    /**
     * Headers safe to persist for debugging — never store Authorization or cookies.
     *
     * @var array<int, string>
     */
    private const ALLOWED_HEADERS = [
        'X-GitHub-Event', 'X-GitHub-Delivery', 'X-Hub-Signature-256',
        'Stripe-Signature',
        'X-Slack-Retry-Num', 'X-Slack-Signature',
        'Content-Type',
    ];

    public function __construct(private readonly TriggerFiringService $firing) {}

    /**
     * Accept an event for a trigger and queue it if it needs work.
     *
     * Business outcomes are never exceptions here — the returned result says what
     * happened, and callers map that onto their own response.
     *
     * $deliveryId lets a caller supply its own idempotency key rather than having
     * one read out of provider headers. Schedule and polling use this to make
     * "this minute" and "this item" exactly-once through the same unique index
     * that dedupes webhook retries, instead of each inventing its own guard.
     *
     * @param  array<string, mixed>  $payload
     */
    public function accept(
        Trigger $trigger,
        string $source,
        array $payload,
        ?Request $request = null,
        ?string $deliveryId = null,
    ): TriggerIntakeResult {
        $deliveryId ??= $request !== null ? $this->firing->resolveDeliveryId($trigger, $request) : null;

        if ($seen = $this->resolveRedelivery($trigger, $request, $deliveryId)) {
            return $seen;
        }

        if ($request !== null && ! $this->firing->matchesFilters($trigger, $payload, $request->headers->all())) {
            return $this->store($trigger, $source, $payload, $request, $deliveryId, TriggerEventStatus::Filtered);
        }

        // A draft or deleted target cannot become runnable by waiting, so this is
        // settled at intake rather than burning queue attempts on it.
        if (! $this->firing->isRunnable($trigger)) {
            return $this->store($trigger, $source, $payload, $request, $deliveryId, TriggerEventStatus::Skipped, 'Target not runnable');
        }

        $result = $this->store($trigger, $source, $payload, $request, $deliveryId, TriggerEventStatus::Pending);

        if ($result->isQueued()) {
            $this->dispatch($trigger, $result->event);
        }

        return $result;
    }

    /**
     * Record a delivery that failed signature verification.
     *
     * Stored without its delivery id on purpose: a rejected delivery must not
     * occupy the dedupe slot, or an attacker could block a legitimate delivery
     * simply by guessing its id and sending a badly signed request first.
     */
    public function reject(Trigger $trigger, string $source, Request $request, string $reason): TriggerEvent
    {
        return TriggerEvent::create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'status' => TriggerEventStatus::Rejected,
            'payload_snippet' => Str::limit($request->getContent(), 5000),
            'headers' => $this->allowedHeaders($request),
            'error' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Record a fault that stopped events being collected at all, rather than one
     * event failing — an unreachable poll URL, an expired credential.
     *
     * Stored with no delivery id so it never occupies a dedupe slot: this is a
     * log entry about the attempt, not an event anyone can redeliver.
     */
    public function recordFailure(Trigger $trigger, string $source, string $error): TriggerEvent
    {
        return TriggerEvent::create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'status' => TriggerEventStatus::Failed,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * Queue an event that is already stored — used by the reconciler to recover
     * work whose job was lost, and when a previously failed delivery is resent.
     */
    public function dispatch(Trigger $trigger, TriggerEvent $event): void
    {
        ProcessTriggerEvent::dispatch($event->id, $trigger->id)
            ->onQueue($this->queueFor($trigger));
    }

    /**
     * Agent runs block on a model call for as long as the model takes, so they get
     * their own queue: a slow agent must not stall workflow events behind it.
     */
    private function queueFor(Trigger $trigger): string
    {
        return $trigger->triggerable_type === (new Agent)->getMorphClass()
            ? (string) config('triggers.queues.agent')
            : (string) config('triggers.queues.default');
    }

    /**
     * Decide whether this delivery is one already accepted, returning a result if
     * so and null to carry on with a fresh event.
     *
     * A previously failed event is re-queued rather than ignored — the provider
     * resending it is a second chance to get that event through, which is exactly
     * what a provider retry is for.
     */
    private function resolveRedelivery(Trigger $trigger, ?Request $request, ?string $deliveryId): ?TriggerIntakeResult
    {
        $existing = $this->firing->existingDelivery($trigger, $deliveryId);

        if ($existing === null) {
            $slackRetry = $request !== null && $request->header('X-Slack-Retry-Num') !== null
                ? $this->latestMatched($trigger)
                : null;

            return $slackRetry !== null ? $this->asDuplicate($slackRetry) : null;
        }

        if ($existing->status === TriggerEventStatus::Failed) {
            $existing->update(['status' => TriggerEventStatus::Pending, 'error' => null]);
            $this->dispatch($trigger, $existing);

            return new TriggerIntakeResult(TriggerEventStatus::Pending, $existing);
        }

        return $this->asDuplicate($existing);
    }

    /**
     * Duplicates are counted on the row they duplicate rather than stored as new
     * rows — the unique index means a second row for the same delivery cannot
     * exist, and "GitHub retried this four times" is the more useful record.
     */
    private function asDuplicate(TriggerEvent $event): TriggerIntakeResult
    {
        $event->increment('duplicate_count');

        return new TriggerIntakeResult(TriggerEventStatus::Duplicate, $event);
    }

    /**
     * Slack sends no stable delivery id — fall back to its retry-num header as a
     * heuristic: a marked retry arriving soon after a match is the same event.
     */
    private function latestMatched(Trigger $trigger): ?TriggerEvent
    {
        return $trigger->triggerEvents()
            ->where('status', TriggerEventStatus::Matched)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(
        Trigger $trigger,
        string $source,
        array $payload,
        ?Request $request,
        ?string $deliveryId,
        TriggerEventStatus $status,
        ?string $error = null,
    ): TriggerIntakeResult {
        $attributes = [
            'trigger_id' => $trigger->id,
            'source' => $source,
            'status' => $status,
            'payload' => $payload,
            'payload_snippet' => $request !== null ? Str::limit($request->getContent(), 5000) : null,
            'headers' => $request !== null ? $this->allowedHeaders($request) : null,
            'error' => $error,
            'delivery_id' => $deliveryId,
            'processed_at' => $status->isTerminal() ? now() : null,
        ];

        try {
            return new TriggerIntakeResult($status, TriggerEvent::create($attributes));
        } catch (UniqueConstraintViolationException $e) {
            // Another delivery of the same id committed between the check above and
            // this insert. Its row is the record of this event.
            $winner = $this->firing->existingDelivery($trigger, $deliveryId);

            if ($winner === null) {
                throw $e;
            }

            return $this->asDuplicate($winner);
        }
    }

    /**
     * @return array<string, string>
     */
    private function allowedHeaders(Request $request): array
    {
        $headers = [];

        foreach (self::ALLOWED_HEADERS as $name) {
            $value = $request->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
