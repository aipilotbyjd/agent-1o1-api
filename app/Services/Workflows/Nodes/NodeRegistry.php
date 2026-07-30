<?php

namespace App\Services\Workflows\Nodes;

use App\Enums\Workflows\WorkflowStepType;
use App\Exceptions\Workflows\UnknownNodeException;

class NodeRegistry
{
    /**
     * @var array<string, NodeDefinition>
     */
    private array $definitions = [];

    public function register(NodeDefinition $definition): void
    {
        $this->definitions[$definition->type()] = $definition;
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    /**
     * @throws UnknownNodeException
     */
    public function get(string $type): NodeDefinition
    {
        return $this->definitions[$type] ?? throw new UnknownNodeException($type);
    }

    /**
     * @return array<string, NodeDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, NodeDefinition>
     */
    public function forStepType(WorkflowStepType $stepType): array
    {
        return array_filter(
            $this->definitions,
            fn (NodeDefinition $definition): bool => $definition->stepType() === $stepType,
        );
    }

    /**
     * The connector nodes a `tool` step can be pointed at.
     *
     * @return array<string, NodeDefinition>
     */
    public function connectors(): array
    {
        return array_filter(
            $this->definitions,
            fn (NodeDefinition $definition): bool => $definition instanceof ExecutableNode,
        );
    }
}
