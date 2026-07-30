<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;

class MergeStepHandler implements StepHandler
{
    /**
     * Join point for parallel branches. The engine only invokes this handler once every
     * incoming branch has completed (see GraphAdvancer::allComplete), so this
     * just collects each branch's output keyed by its step key.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $branches = $step['config']['branches'] ?? [];
        $stepOutputs = $context['steps'] ?? [];

        $merged = [];
        foreach ($branches as $branchKey) {
            $merged[$branchKey] = $stepOutputs[$branchKey] ?? null;
        }

        return ['output' => ['branches' => $merged]];
    }
}
