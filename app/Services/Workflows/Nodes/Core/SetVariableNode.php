<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Models\Runs\Run;
use App\Models\Variable;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

class SetVariableNode extends NodeDefinition implements ExecutableNode
{
    public function type(): string
    {
        return 'set_variable';
    }

    public function name(): string
    {
        return 'Set Variable';
    }

    public function description(): string
    {
        return 'Set a workspace or execution-scoped variable.';
    }

    public function category(): string
    {
        return 'actions';
    }

    public function icon(): string
    {
        return 'edit';
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
            'key' => ['type' => 'string'],
            'value' => ['type' => 'string'],
            'scope' => ['type' => 'string', 'enum' => ['workspace', 'execution'], 'default' => 'workspace'],
        ], 'required' => ['key', 'value']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'key' => ['type' => 'string'],
            'value' => ['type' => 'string'],
            'scope' => ['type' => 'string'],
        ]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $config['key'] ?? '';
        $value = $config['value'] ?? '';
        $scope = $config['scope'] ?? 'workspace';

        if ($scope === 'workspace') {
            Variable::updateOrCreate(
                ['workspace_id' => $run->workspace_id, 'key' => $key],
                ['value' => is_array($value) ? json_encode($value) : (string) $value, 'created_by' => 1],
            );
        }

        return ['key' => $key, 'value' => $value, 'scope' => $scope];
    }
}
