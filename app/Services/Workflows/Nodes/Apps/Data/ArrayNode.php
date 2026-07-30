<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use RuntimeException;

class ArrayNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'array.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Array Utilities';
    }

    public function description(): string
    {
        return 'Manipulate arrays: pluck, filter, sort, slice, merge, and more.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'list';
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
                'key' => ['type' => 'string'],
                'offset' => ['type' => 'integer'],
                'length' => ['type' => 'integer'],
                'size' => ['type' => 'integer'],
                'depth' => ['type' => 'integer'],
                'with' => ['type' => 'array'],
                'separator' => ['type' => 'string'],
                'descending' => ['type' => 'boolean'],
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
        $items = (array) ($config['items'] ?? []);

        return match ($operation) {
            'pluck' => ['result' => array_column($items, $config['key'] ?? null)],
            'filter' => ['result' => array_values(array_filter($items))],
            'unique' => ['result' => array_values(array_unique($items, SORT_REGULAR))],
            'sort' => $this->sort($items, $config),
            'reverse' => ['result' => array_reverse($items)],
            'slice' => ['result' => array_slice($items, (int) ($config['offset'] ?? 0), isset($config['length']) ? (int) $config['length'] : null)],
            'chunk' => ['result' => array_chunk($items, max(1, (int) ($config['size'] ?? 1)))],
            'flatten' => ['result' => collect($items)->flatten((int) ($config['depth'] ?? 1))->all()],
            'merge' => ['result' => array_merge($items, (array) ($config['with'] ?? []))],
            'count' => ['result' => count($items)],
            'first' => ['result' => array_values($items)[0] ?? null],
            'last' => ['result' => array_values($items)[count($items) - 1] ?? null],
            'join' => ['result' => implode($config['separator'] ?? ',', $items)],
            default => throw new RuntimeException("Array: unknown operation '{$operation}'"),
        };
    }

    private function sort(array $items, array $config): array
    {
        $key = $config['key'] ?? null;
        $descending = (bool) ($config['descending'] ?? false);

        return [
            'result' => collect($items)
                ->when($key, fn ($c) => $descending ? $c->sortByDesc($key) : $c->sortBy($key))
                ->when(! $key, fn ($c) => $descending ? $c->sortDesc() : $c->sort())
                ->values()
                ->all(),
        ];
    }
}
