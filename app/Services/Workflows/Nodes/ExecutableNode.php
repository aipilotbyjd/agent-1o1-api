<?php

namespace App\Services\Workflows\Nodes;

use App\Models\Runs\Run;

/**
 * Implemented by connector nodes that do their own work.
 *
 * Flow-control nodes (condition, merge, delay, loop, approval, sub-workflow) are driven
 * by the engine itself and deliberately do not implement this — their behaviour is graph
 * traversal, not a call out to something.
 */
interface ExecutableNode
{
    /**
     * @param  array<string, mixed>  $config  The step's config, already template-resolved.
     * @param  array<string, mixed>  $context  The run context (input, steps, variables).
     * @return array<string, mixed> The step output, readable by later steps via dot paths.
     */
    public function execute(Run $run, array $config, array $context): array;
}
