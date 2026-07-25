<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Run;
use App\Models\Trigger;
use App\Models\Workflow;
use App\Services\Workflows\WorkflowRunner;

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
     * @param  array<string, mixed>  $payload
     */
    public function fire(Trigger $trigger, array $payload, string $triggerType): ?Run
    {
        $target = $trigger->triggerable;

        $run = match (true) {
            $target instanceof Workflow && $target->status === 'published' && ! $target->trashed() => $this->workflows->start($target, null, $payload, $triggerType),
            $target instanceof Agent && ! $target->trashed() => $this->agents->sendFromTrigger($target, $payload, $triggerType, $trigger->config['message'] ?? null),
            default => null,
        };

        if ($run !== null) {
            $trigger->update(['last_run_at' => now()]);
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
}
