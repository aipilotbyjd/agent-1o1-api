<?php

namespace App\Services\Triggers;

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Triggers\TriggerEvent;

/**
 * What intake did with one inbound delivery, and the row that records it.
 *
 * The outcome is deliberately separate from `$event->status`: a retried delivery
 * is a Duplicate outcome, but the row it points at is whatever the original
 * delivery became — usually Matched. Collapsing the two would make a duplicate of
 * a successful event indistinguishable from a fresh success.
 */
final readonly class TriggerIntakeResult
{
    public function __construct(
        public TriggerEventStatus $outcome,
        public TriggerEvent $event,
    ) {}

    /**
     * Whether this delivery produced work for a queue worker to pick up.
     */
    public function isQueued(): bool
    {
        return $this->outcome === TriggerEventStatus::Pending;
    }
}
