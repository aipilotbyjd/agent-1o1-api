<?php

namespace App\Services\Workflows\Nodes\Connectors;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\ExpressionEvaluator;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

/**
 * Computes named values from sandboxed expressions.
 *
 * This deliberately evaluates a small expression language rather than arbitrary PHP or
 * JavaScript: step config is workspace-authored, so running it as real code would hand
 * any workspace member execution on the application host. See ExpressionEvaluator for
 * the complete set of operations available.
 */
class CodeExpressionNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'code.expression';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Compute Values';
    }

    public function description(): string
    {
        return 'Compute named values from sandboxed expressions over the run context.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'calculator';
    }

    public function color(): string
    {
        return '#0ea5e9';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['expressions'],
            'properties' => [
                'expressions' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    /**
     * Expressions reference the context directly (`input.count`) rather than through
     * `{{ }}`, so template resolution passes over them untouched and the evaluator
     * sees the original source.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function execute(Run $run, array $config, array $context): array
    {
        $evaluator = app(ExpressionEvaluator::class);
        $output = [];

        foreach ($config['expressions'] ?? [] as $name => $expression) {
            $output[$name] = $evaluator->evaluate((string) $expression, $context);
        }

        return $output;
    }
}
