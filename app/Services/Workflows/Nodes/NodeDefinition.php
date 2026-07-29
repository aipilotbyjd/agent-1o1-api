<?php

namespace App\Services\Workflows\Nodes;

use App\Enums\Workflows\WorkflowStepType;

/**
 * The single source of truth for one kind of workflow step.
 *
 * A definition carries everything both consumers need: the builder palette reads its
 * presentation and schemas, and the engine reads its step type (and, for connectors,
 * its execute()). The `nodes` table is a projection of these, not a parallel registry.
 */
abstract class NodeDefinition
{
    /**
     * The stable identifier, namespaced by family — e.g. "slack.post_message".
     */
    abstract public function type(): string;

    /**
     * Which execution kind the engine treats this node as.
     */
    abstract public function stepType(): WorkflowStepType;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * The node_categories slug this node is filed under in the palette.
     */
    abstract public function category(): string;

    /**
     * A JSON-schema-shaped description of the step's `config`, validated whenever a
     * graph is saved or published.
     *
     * @return array<string, mixed>
     */
    abstract public function configSchema(): array;

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(): array
    {
        return [];
    }

    public function icon(): string
    {
        return 'box';
    }

    public function color(): string
    {
        return '#6366f1';
    }

    public function version(): int
    {
        return 1;
    }

    /**
     * The credential_types key this node expects, when it needs one.
     */
    public function credentialType(): ?string
    {
        return null;
    }

    public function isPremium(): bool
    {
        return false;
    }

    public function docsUrl(): ?string
    {
        return null;
    }
}
