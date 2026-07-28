<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;

class DelayStepHandler implements StepHandler
{
    /**
     * The delay itself is applied by the engine when dispatching the next steps.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        return ['output' => ['seconds' => (int) ($step['config']['seconds'] ?? 0)]];
    }
}
