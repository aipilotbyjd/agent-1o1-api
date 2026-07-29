<?php

namespace App\Providers;

use App\Services\Workflows\Nodes\Connectors\CodeExpressionNode;
use App\Services\Workflows\Nodes\Connectors\CustomHttpNode;
use App\Services\Workflows\Nodes\Connectors\EmailSendNode;
use App\Services\Workflows\Nodes\Connectors\HttpRequestNode;
use App\Services\Workflows\Nodes\Connectors\PostgresQueryNode;
use App\Services\Workflows\Nodes\Connectors\SlackPostMessageNode;
use App\Services\Workflows\Nodes\Core\AgentNode;
use App\Services\Workflows\Nodes\Core\ConditionNode;
use App\Services\Workflows\Nodes\Core\DelayNode;
use App\Services\Workflows\Nodes\Core\HumanApprovalNode;
use App\Services\Workflows\Nodes\Core\LoopNode;
use App\Services\Workflows\Nodes\Core\MergeNode;
use App\Services\Workflows\Nodes\Core\SubWorkflowNode;
use App\Services\Workflows\Nodes\Core\ToolNode;
use App\Services\Workflows\Nodes\Core\TransformNode;
use App\Services\Workflows\Nodes\NodeDefinition;
use App\Services\Workflows\Nodes\NodeRegistry;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Every node the engine knows how to run. The `nodes` table is synced from this
     * list (see NodeCatalogSync), so adding a node here is all that is required for it
     * to appear in the builder palette and be validated at publish time.
     *
     * @var array<int, class-string<NodeDefinition>>
     */
    private const NODES = [
        // Core flow control — executed by the engine itself.
        AgentNode::class,
        ToolNode::class,
        ConditionNode::class,
        MergeNode::class,
        TransformNode::class,
        DelayNode::class,
        LoopNode::class,
        HumanApprovalNode::class,
        SubWorkflowNode::class,

        // Connectors — executed by their own definition.
        HttpRequestNode::class,
        CustomHttpNode::class,
        SlackPostMessageNode::class,
        EmailSendNode::class,
        PostgresQueryNode::class,
        CodeExpressionNode::class,
    ];

    public function register(): void
    {
        $this->app->singleton(NodeRegistry::class, function (): NodeRegistry {
            $registry = new NodeRegistry;

            foreach (self::NODES as $node) {
                $registry->register(new $node);
            }

            return $registry;
        });
    }
}
