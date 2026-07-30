<?php

namespace App\Services\Workflows\Nodes;

use App\Exceptions\Workflows\UnknownNodeException;
use App\Models\Nodes\Node;
use App\Services\Workflows\Nodes\Custom\CustomNode;

/**
 * Looks a node type up across both places one can live: the code registry, and the
 * `nodes` table for workspace-authored rows.
 *
 * NodeRegistry stays a pure in-memory catalog of the definitions shipped in code. This
 * sits in front of it so callers — the engine, the builder, the agent tool layer — do
 * not have to care which kind they were given.
 */
class NodeResolver
{
    /**
     * @var array<string, CustomNode>
     */
    private array $customCache = [];

    public function __construct(private readonly NodeRegistry $registry) {}

    public function has(string $type): bool
    {
        return $this->registry->has($type) || $this->custom($type) !== null;
    }

    /**
     * @throws UnknownNodeException
     */
    public function definition(string $type): NodeDefinition
    {
        if ($this->registry->has($type)) {
            return $this->registry->get($type);
        }

        return $this->custom($type) ?? throw new UnknownNodeException($type);
    }

    /**
     * @throws UnknownNodeException
     */
    public function executable(string $type): ExecutableNode
    {
        $definition = $this->definition($type);

        if (! $definition instanceof ExecutableNode) {
            throw new UnknownNodeException($type, "Node [{$type}] is not executable.");
        }

        return $definition;
    }

    /**
     * Custom rows are resolved per type and memoised, so a loop running the same node
     * hundreds of times does not re-query for each iteration.
     */
    private function custom(string $type): ?CustomNode
    {
        if (array_key_exists($type, $this->customCache)) {
            return $this->customCache[$type];
        }

        $node = Node::query()
            ->with('category')
            ->where('type', $type)
            ->where('is_custom', true)
            ->where('is_active', true)
            ->first();

        return $this->customCache[$type] = $node === null ? null : new CustomNode($node);
    }
}
