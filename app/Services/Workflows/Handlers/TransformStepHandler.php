<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\TemplateResolver;

class TransformStepHandler implements StepHandler
{
    public function __construct(public TemplateResolver $templates) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        return ['output' => $this->templates->resolveArray($step['config']['mapping'] ?? [], $context)];
    }
}
