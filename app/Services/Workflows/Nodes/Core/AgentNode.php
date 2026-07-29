<?php

namespace App\Services\Workflows\Nodes\Core;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\NodeDefinition;

class AgentNode extends NodeDefinition
{
    public function type(): string
    {
        return 'core.agent';
    }

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Agent;
    }

    public function name(): string
    {
        return 'Agent';
    }

    public function description(): string
    {
        return 'Prompt an agent and capture its response.';
    }

    public function category(): string
    {
        return 'ai';
    }

    public function icon(): string
    {
        return 'sparkles';
    }

    public function color(): string
    {
        return '#8b5cf6';
    }

    /**
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                'agent_id' => ['type' => 'integer'],
                'prompt' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'agent_version' => ['type' => 'integer'],
            ],
        ];
    }
}
