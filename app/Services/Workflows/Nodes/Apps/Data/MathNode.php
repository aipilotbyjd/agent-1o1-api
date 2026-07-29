<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

class MathNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'math.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Math Utilities';
    }

    public function description(): string
    {
        return 'Perform arithmetic and statistical operations.';
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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation'],
            'properties' => [
                'operation' => ['type' => 'string'],
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
                'values' => ['type' => 'array'],
                'precision' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = $config['operation'] ?? 'default';
        $a = (float) ($config['a'] ?? 0);
        $b = (float) ($config['b'] ?? 0);
        $values = array_map(floatval(...), (array) ($config['values'] ?? []));

        return match ($operation) {
            'add' => ['result' => $a + $b],
            'subtract' => ['result' => $a - $b],
            'multiply' => ['result' => $a * $b],
            'divide' => $b == 0.0 ? throw new \RuntimeException('Math: division by zero') : ['result' => $a / $b],
            'modulo' => ['result' => fmod($a, $b)],
            'power' => ['result' => $a ** $b],
            'sqrt' => ['result' => sqrt($a)],
            'abs' => ['result' => abs($a)],
            'round' => ['result' => round($a, (int) ($config['precision'] ?? 0))],
            'floor' => ['result' => floor($a)],
            'ceil' => ['result' => ceil($a)],
            'sum' => ['result' => array_sum($values)],
            'avg' => ['result' => count($values) > 0 ? array_sum($values) / count($values) : 0],
            'min' => ['result' => count($values) > 0 ? min($values) : null],
            'max' => ['result' => count($values) > 0 ? max($values) : null],
            'random' => ['result' => random_int((int) $a, (int) max($a, $b))],
            default => throw new \RuntimeException("Math: unknown operation '{$operation}'"),
        };
    }
}
