<?php

namespace App\Services\Triggers;

use App\Enums\Runs\RunStatus;
use App\Jobs\Triggers\ProcessTriggerEvent;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Workflows\Workflow;
use App\Services\Agents\AgentChatService;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\Request;

class TriggerFiringService
{
    public function __construct(
        public WorkflowRunner $workflows,
        public AgentChatService $agents,
    ) {}

    /**
     * Fire a trigger against its workflow or agent. Returns null when the
     * target is not currently runnable (draft, deleted, or unknown type).
     *
     * Exceptions are left to propagate untouched. Counting them against the
     * trigger's failure streak here would mean a single flaky event could trip
     * the circuit breaker across its own retries, so the streak is advanced by
     * whoever owns the decision that an event is finally, permanently failed —
     * {@see ProcessTriggerEvent::failed()}.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fire(Trigger $trigger, array $payload, string $triggerType): ?Run
    {
        $target = $trigger->triggerable;

        $run = match (true) {
            $this->isRunnableWorkflow($target) => $this->workflows->start($target, null, $payload, $triggerType),
            $this->isRunnableAgent($target) => $this->agents->sendFromTrigger($target, $payload, $triggerType, $trigger->config['message'] ?? null),
            default => null,
        };

        if ($run !== null) {
            $trigger->update(['last_run_at' => now(), 'consecutive_failure_count' => 0]);
        }

        return $run;
    }

    /**
     * Whether firing this trigger right now would produce a run.
     *
     * Checked at intake so a trigger pointing at a draft or deleted target fails
     * immediately instead of occupying the queue — the answer cannot change by
     * retrying, so there is nothing to gain from accepting the event.
     */
    public function isRunnable(Trigger $trigger): bool
    {
        $target = $trigger->triggerable;

        return $this->isRunnableWorkflow($target) || $this->isRunnableAgent($target);
    }

    private function isRunnableWorkflow(mixed $target): bool
    {
        return $target instanceof Workflow && $target->status === 'published' && ! $target->trashed();
    }

    private function isRunnableAgent(mixed $target): bool
    {
        return $target instanceof Agent && ! $target->trashed();
    }

    /**
     * Evaluate the trigger's config filters against an incoming webhook.
     * All filters must match; a trigger without filters always matches.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function matchesFilters(Trigger $trigger, array $payload, array $headers): bool
    {
        foreach ($trigger->config['filters'] ?? [] as $filter) {
            $actual = ($filter['source'] ?? 'payload') === 'header'
                ? ($headers[strtolower($filter['path'])][0] ?? null)
                : data_get($payload, $filter['path']);

            $actual = is_scalar($actual) ? (string) $actual : null;
            $expected = (string) $filter['value'];

            $matches = match ($filter['operator'] ?? 'equals') {
                'not_equals' => $actual !== $expected,
                'contains' => $actual !== null && str_contains($actual, $expected),
                default => $actual === $expected,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve the provider delivery id used to dedupe retried webhook
     * deliveries, from either a header or a payload path on the trigger type.
     */
    public function resolveDeliveryId(Trigger $trigger, Request $request): ?string
    {
        $type = $trigger->triggerType;

        if ($type?->dedupe_header !== null) {
            $value = $request->header($type->dedupe_header);

            if ($value !== null) {
                return $value;
            }
        }

        if ($type?->dedupe_payload_path !== null) {
            $value = data_get($request->all(), $type->dedupe_payload_path);

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * The event already stored for this delivery id, if any.
     *
     * A fast path only — the unique index on (trigger_id, delivery_id) is what
     * actually guarantees dedupe, since two concurrent deliveries can both pass
     * this check before either has written its row.
     */
    public function existingDelivery(Trigger $trigger, ?string $deliveryId): ?TriggerEvent
    {
        if ($deliveryId === null) {
            return null;
        }

        return TriggerEvent::query()
            ->where('trigger_id', $trigger->id)
            ->where('delivery_id', $deliveryId)
            ->first();
    }

    /**
     * Whether the trigger's target already has a non-terminal run in
     * flight — used to avoid spawning overlapping runs of the same target.
     */
    public function hasInFlightRun(Trigger $trigger): bool
    {
        return Run::query()
            ->where('runnable_type', $trigger->triggerable_type)
            ->where('runnable_id', $trigger->triggerable_id)
            ->whereNotIn('status', [RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled])
            ->exists();
    }
}
