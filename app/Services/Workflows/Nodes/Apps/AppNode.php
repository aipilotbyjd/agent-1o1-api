<?php

namespace App\Services\Workflows\Nodes\Apps;

use App\Enums\Workflows\WorkflowStepType;
use App\Services\Workflows\Nodes\Concerns\RecordsConnectorMetrics;
use App\Services\Workflows\Nodes\Concerns\ResolvesCredentials;
use App\Services\Workflows\Nodes\ExecutableNode;
use App\Services\Workflows\Nodes\NodeDefinition;

/**
 * The base for every third-party integration node under `Apps/`.
 *
 * App nodes are always `tool` steps that the engine invokes directly, and they all
 * authenticate through a workspace credential and report their latency, so those three
 * decisions live here rather than being restated by each integration.
 *
 * Engine-driven nodes (`Flow/`) and first-party primitives (`Core/`) extend
 * {@see NodeDefinition} directly instead.
 */
abstract class AppNode extends NodeDefinition implements ExecutableNode
{
    use RecordsConnectorMetrics;
    use ResolvesCredentials;

    public function stepType(): WorkflowStepType
    {
        return WorkflowStepType::Tool;
    }

    public function category(): string
    {
        return 'actions';
    }
}
