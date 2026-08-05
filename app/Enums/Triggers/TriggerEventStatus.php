<?php

namespace App\Enums\Triggers;

/**
 * The lifecycle of a single inbound trigger event.
 *
 * Events are persisted at intake before any work happens, so this status — not
 * the presence of a run — is the record of what became of an event. Anything
 * left in a non-terminal state past its grace period is what
 * ReconcileTriggerEventsCommand re-dispatches.
 */
enum TriggerEventStatus: string
{
    /** Accepted and durably stored, waiting for a worker to pick it up. */
    case Pending = 'pending';

    /** A worker is firing it right now. */
    case Processing = 'processing';

    /** Fired successfully; run_id points at the resulting run. */
    case Matched = 'matched';

    /** The trigger's config filters rejected it. Never queued — not a failure. */
    case Filtered = 'filtered';

    /** A retry of a delivery already accepted under the same delivery id. */
    case Duplicate = 'duplicate';

    /** Signature verification failed. */
    case Rejected = 'rejected';

    /** The target was draft, deleted, or otherwise not runnable at intake. */
    case Skipped = 'skipped';

    /** Every retry was exhausted without starting a run. */
    case Failed = 'failed';

    /**
     * Whether this event has reached a state it will never leave on its own.
     */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Pending, self::Processing], true);
    }

    /**
     * Whether this event should be handed to a queue worker.
     */
    public function isQueueable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Statuses that represent an event the system accepted responsibility for
     * but has not yet resolved — the reconciler's search space.
     *
     * @return array<int, string>
     */
    public static function unresolved(): array
    {
        return [self::Pending->value, self::Processing->value];
    }
}
