<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use RuntimeException;

class JsonNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'json.utility';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function name(): string
    {
        return 'JSON Utilities';
    }

    public function description(): string
    {
        return 'Parse, stringify, and manipulate JSON data.';
    }

    public function category(): string
    {
        return 'data';
    }

    public function icon(): string
    {
        return 'file-json';
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
                'json' => ['type' => 'string'],
                'data' => ['type' => 'object'],
                'path' => ['type' => 'string'],
                'with' => ['type' => 'object'],
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

        return match ($operation) {
            'parse' => $this->parse($config),
            'stringify' => ['result' => json_encode($config['data'] ?? null)],
            'extract' => ['result' => data_get($config['data'] ?? [], $config['path'] ?? '')],
            'merge' => ['result' => array_merge((array) ($config['data'] ?? []), (array) ($config['with'] ?? []))],
            'keys' => ['result' => array_keys((array) ($config['data'] ?? []))],
            'values' => ['result' => array_values((array) ($config['data'] ?? []))],
            default => throw new RuntimeException("Json: unknown operation '{$operation}'"),
        };
    }

    private function parse(array $config): array
    {
        $decoded = json_decode($config['json'] ?? '', true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON parse error: '.json_last_error_msg());
        }

        return ['result' => $decoded];
    }
}
