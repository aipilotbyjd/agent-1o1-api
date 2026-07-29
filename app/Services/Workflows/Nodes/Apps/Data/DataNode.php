<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

class DataNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'data.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'Data Manipulation';
    }

    public function description(): string
    {
        return 'Get, set, pick, omit, and rename keys on data structures.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'database';
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
                'data' => ['type' => 'object'],
                'path' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'keys' => ['type' => 'array'],
                'mapping' => ['type' => 'object'],
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
        $data = $config['data'] ?? $context;

        return match ($operation) {
            'get' => ['result' => data_get($data, $config['path'] ?? '')],
            'set' => $this->set($data, $config),
            'pick' => ['result' => array_intersect_key((array) $data, array_flip((array) ($config['keys'] ?? [])))],
            'omit' => ['result' => array_diff_key((array) $data, array_flip((array) ($config['keys'] ?? [])))],
            'rename_keys' => $this->renameKeys((array) $data, $config),
            'default' => ['result' => $data],
            default => throw new \RuntimeException("Data: unknown operation '{$operation}'"),
        };
    }

    private function set(mixed $data, array $config): array
    {
        $result = (array) $data;
        data_set($result, $config['path'] ?? '', $config['value'] ?? null);

        return ['result' => $result];
    }

    private function renameKeys(array $data, array $config): array
    {
        $mapping = (array) ($config['mapping'] ?? []);
        $result = [];

        foreach ($data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return ['result' => $result];
    }
}
