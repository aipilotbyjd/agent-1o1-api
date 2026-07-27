<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Run;

interface StepHandler
{
    /**
     * Execute the snapshot step and return its output (plus optional token usage).
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>, usage?: array<string, mixed>|null}
     */
    public function handle(Run $run, array $step, array $context): array;
}
