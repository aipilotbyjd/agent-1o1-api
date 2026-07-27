<?php

namespace App\Services\Workflows;

use App\Models\Workflow;

class ContractGenerator
{
    /**
     * Generate a contract snapshot payload from a workflow's current (live draft) graph.
     *
     * @return array{node_signature: array<string, mixed>, input_schema: null, output_schema: null}
     */
    public function generate(Workflow $workflow): array
    {
        return [
            'node_signature' => $this->signatureFor($workflow),
            'input_schema' => null,
            'output_schema' => null,
        ];
    }

    /**
     * Build a deterministic node signature for the workflow's current graph — stable
     * regardless of array insertion order, so it can be diffed reliably later.
     *
     * @return array{steps: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function signatureFor(Workflow $workflow): array
    {
        $graph = $workflow->graphArray();

        $steps = collect($graph['steps'])
            ->map(fn (array $step): array => [
                'key' => $step['key'],
                'type' => $step['type'],
                'config_keys' => collect(array_keys($step['config'] ?? []))->sort()->values()->all(),
            ])
            ->sortBy('key')
            ->values()
            ->all();

        $edges = collect($graph['edges'])
            ->map(fn (array $edge): array => [
                'from' => $edge['from'],
                'to' => $edge['to'],
            ])
            ->sortBy(fn (array $edge): string => "{$edge['from']}->{$edge['to']}")
            ->values()
            ->all();

        return ['steps' => $steps, 'edges' => $edges];
    }

    /**
     * Diff a baseline node signature against a freshly generated one.
     *
     * @param  array{steps: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $baseline
     * @param  array{steps: array<int, array<string, mixed>>, edges?: array<int, array<string, mixed>>}  $current
     * @return array{drifted: bool, added_steps: array<int, string>, removed_steps: array<int, string>, changed_steps: array<int, array<string, mixed>>}
     */
    public function diff(array $baseline, array $current): array
    {
        $baselineSteps = collect($baseline['steps'] ?? [])->keyBy('key');
        $currentSteps = collect($current['steps'] ?? [])->keyBy('key');

        $addedSteps = $currentSteps->keys()->diff($baselineSteps->keys())->values()->all();
        $removedSteps = $baselineSteps->keys()->diff($currentSteps->keys())->values()->all();

        $changedSteps = $baselineSteps->keys()
            ->intersect($currentSteps->keys())
            ->filter(fn (string $key): bool => $baselineSteps[$key] !== $currentSteps[$key])
            ->map(fn (string $key): array => [
                'key' => $key,
                'before' => $baselineSteps[$key],
                'after' => $currentSteps[$key],
            ])
            ->values()
            ->all();

        return [
            'drifted' => $addedSteps !== [] || $removedSteps !== [] || $changedSteps !== [],
            'added_steps' => $addedSteps,
            'removed_steps' => $removedSteps,
            'changed_steps' => $changedSteps,
        ];
    }
}
