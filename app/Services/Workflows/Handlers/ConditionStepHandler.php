<?php

namespace App\Services\Workflows\Handlers;

use App\Models\Runs\Run;
use App\Services\Workflows\TemplateResolver;

class ConditionStepHandler implements StepHandler
{
    public function __construct(public TemplateResolver $templates) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $context
     * @return array{output: array<string, mixed>}
     */
    public function handle(Run $run, array $step, array $context): array
    {
        $config = $step['config'] ?? [];

        $left = $this->templates->resolve($config['field'] ?? '', $context);
        $operator = $config['operator'] ?? 'truthy';
        $right = (string) ($config['value'] ?? '');

        $result = match ($operator) {
            'equals' => $left === $right,
            'not_equals' => $left !== $right,
            'contains' => str_contains($left, $right),
            'gt' => is_numeric($left) && (float) $left > (float) $right,
            'gte' => is_numeric($left) && (float) $left >= (float) $right,
            'lt' => is_numeric($left) && (float) $left < (float) $right,
            'lte' => is_numeric($left) && (float) $left <= (float) $right,
            default => filter_var($left, FILTER_VALIDATE_BOOLEAN) || is_numeric($left) && (float) $left !== 0.0,
        };

        return ['output' => ['result' => $result ? 'true' : 'false']];
    }
}
