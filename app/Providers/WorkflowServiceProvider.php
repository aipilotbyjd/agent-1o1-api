<?php

namespace App\Providers;

use App\Services\Workflows\Nodes\Apps\Mail\EmailSendNode;
use App\Services\Workflows\Nodes\Apps\Postgres\PostgresQueryNode;
use App\Services\Workflows\Nodes\Apps\Slack\SlackPostMessageNode;
use App\Services\Workflows\Nodes\Core\AgentNode;
use App\Services\Workflows\Nodes\Core\CodeNode;
use App\Services\Workflows\Nodes\Core\CustomHttpNode;
use App\Services\Workflows\Nodes\Core\HttpRequestNode;
use App\Services\Workflows\Nodes\Core\HumanApprovalNode;
use App\Services\Workflows\Nodes\Core\SubWorkflowNode;
use App\Services\Workflows\Nodes\Core\ToolNode;
use App\Services\Workflows\Nodes\Core\TransformNode;
use App\Services\Workflows\Nodes\Flow\ConditionNode;
use App\Services\Workflows\Nodes\Flow\DelayNode;
use App\Services\Workflows\Nodes\Flow\LoopNode;
use App\Services\Workflows\Nodes\Flow\MergeNode;
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
        SlackPostMessageNode::class,
        EmailSendNode::class,
        PostgresQueryNode::class,
        HttpRequestNode::class,
        CustomHttpNode::class,
        CodeNode::class,
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
