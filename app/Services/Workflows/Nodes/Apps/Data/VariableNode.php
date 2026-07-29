<?php

namespace App\Services\Workflows\Nodes\Apps\Data;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Models\Variable;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

class VariableNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'variable';
    }

    public function name(): string
    {
        return 'Variable';
    }

    public function description(): string
    {
        return 'Get a workspace variable or list all non-secret variables.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'database';
    }

    public function color(): string
    {
        return '#84cc16';
    }

    public function credentialType(): ?string
    {
        return null;
    }

    public function docsUrl(): ?string
    {
        return null;
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'operation' => ['type' => 'string', 'enum' => ['get', 'list'], 'default' => 'get'],
            'key' => ['type' => 'string'],
            'default' => ['type' => 'string'],
        ], 'required' => ['operation']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'key' => ['type' => 'string'],
            'value' => ['type' => 'string'],
        ]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $operation = $config['operation'] ?? 'get';
        $workspaceId = $run->workspace_id;
        $key = $config['key'] ?? '';

        if ($operation === 'list') {
            $variables = Variable::where('workspace_id', $workspaceId)
                ->where('is_secret', false)
                ->pluck('value', 'key');

            return ['variables' => $variables->all()];
        }

        $variable = Variable::where('workspace_id', $workspaceId)->where('key', $key)->first();

        return [
            'key' => $key,
            'value' => $variable ? $variable->resolvedValue() : ($config['default'] ?? null),
        ];
    }
}
