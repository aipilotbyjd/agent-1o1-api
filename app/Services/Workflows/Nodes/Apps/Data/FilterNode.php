<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

class FilterNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'filter.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Filter Data';
    }

    public function description(): string
    {
        return 'Filter an array of items by a field and operator comparison.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'filter';
    }

    public function color(): string
    {
        return '#0ea5e9';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['operation', 'items'],
            'properties' => [
                'operation' => ['type' => 'string'],
                'items' => ['type' => 'array'],
                'field' => ['type' => 'string'],
                'filter_operator' => ['type' => 'string'],
                'value' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $items = (array) ($config['items'] ?? []);
        $field = $config['field'] ?? null;
        $filterOperator = $config['filter_operator'] ?? '==';
        $value = $config['value'] ?? null;

        $filtered = array_values(array_filter($items, function ($item) use ($field, $filterOperator, $value) {
            $itemValue = $field !== null ? data_get($item, $field) : $item;

            return match ($filterOperator) {
                '==' => $itemValue == $value,
                '!=' => $itemValue != $value,
                '>' => $itemValue > $value,
                '>=' => $itemValue >= $value,
                '<' => $itemValue < $value,
                '<=' => $itemValue <= $value,
                'contains' => is_string($itemValue) && str_contains($itemValue, (string) $value),
                'in' => is_array($value) && in_array($itemValue, $value),
                'not_null' => $itemValue !== null,
                'is_null' => $itemValue === null,
                default => true,
            };
        }));

        return [
            'result' => $filtered,
            'count' => count($filtered),
            'removed' => count($items) - count($filtered),
        ];
    }
}
