<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkflowBuilder\AddNodeTool;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DryRunWorkflowTool;
use App\Ai\Tools\WorkflowBuilder\InspectNodeSchemaTool;
use App\Ai\Tools\WorkflowBuilder\ListAvailableNodesTool;
use App\Ai\Tools\WorkflowBuilder\RemoveNodeTool;
use App\Ai\Tools\WorkflowBuilder\UpdateNodeTool;
use App\Ai\Tools\WorkflowBuilder\ValidateWorkflowTool;
use App\Models\Workflows\WorkflowBuilderSession;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class WorkflowBuilderAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public WorkflowBuilderSession $session) {}

    public function instructions(): string
    {
        return <<<'TEXT'
        You are a workflow-building assistant. You edit a workflow's draft graph (steps and
        edges) on the user's behalf, using the tools available to you — you never describe
        an edit without actually making it.

        Start from list_available_nodes to see what exists. Before adding a step of a node
        you're not already familiar with in this conversation, call inspect_node_schema with
        that node's "node_type" so the step's "config" matches what it expects — adding a
        step with missing or mistyped config is rejected and you will have to correct it.

        Give every step a short, unique, descriptive key (e.g. "send_confirmation_email").
        Connect steps in the order they should run; for a condition step, add one edge per
        branch with the matching "condition" value. To handle failures, add an edge with the
        condition "error" from the step that might fail.

        Before telling the user the workflow is ready, call validate_workflow, and use
        dry_run_workflow to confirm the wiring resolves — it simulates the graph without
        calling any external service. Fix anything either one reports.

        After each change, briefly confirm in plain language what you did.
        TEXT;
    }

    /**
     * @return iterable<int, mixed>
     */
    public function tools(): iterable
    {
        return [
            new ListAvailableNodesTool($this->session),
            new InspectNodeSchemaTool($this->session),
            new AddNodeTool($this->session),
            new UpdateNodeTool($this->session),
            new RemoveNodeTool($this->session),
            new ConnectNodesTool($this->session),
            new DisconnectNodesTool($this->session),
            new ValidateWorkflowTool($this->session),
            new DryRunWorkflowTool($this->session),
        ];
    }
}
