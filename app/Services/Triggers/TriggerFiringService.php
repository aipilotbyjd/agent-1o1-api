<?php

namespace App\Services\Triggers;

use App\Enums\Runs\RunStatus;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Workflows\Workflow;
use App\Services\Agents\AgentChatService;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\Request;
use Throwable;

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
     * Exceptions raised by the underlying runner are recorded against the
     * trigger's failure streak and rethrown — callers own the HTTP/event
     * response since only they know the right status and event source.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fire(Trigger $trigger, array $payload, string $triggerType): ?Run
    {
        $target = $trigger->triggerable;

        try {
            $run = match (true) {
                $target instanceof Workflow && $target->status === 'published' && ! $target->trashed() => $this->workflows->start($target, null, $payload, $triggerType),
                $target instanceof Agent && ! $target->trashed() => $this->agents->sendFromTrigger($target, $payload, $triggerType, $trigger->config['message'] ?? null),
                default => null,
            };
        } catch (Throwable $e) {
            $trigger->registerFailure();

            throw $e;
        }

        if ($run !== null) {
            $trigger->update(['last_run_at' => now(), 'consecutive_failure_count' => 0]);
        }

        return $run;
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
     * Whether a delivery with this id has already been successfully fired
     * for this trigger — a retried provider delivery, not a new event.
     */
    public function isDuplicateDelivery(Trigger $trigger, ?string $deliveryId): bool
    {
        if ($deliveryId === null) {
            return false;
        }

        return TriggerEvent::query()
            ->where('trigger_id', $trigger->id)
            ->where('delivery_id', $deliveryId)
            ->where('matched', true)
            ->exists();
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
