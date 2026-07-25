<?php

namespace App\Ai;

use App\Ai\Tools\DynamicTool;
use App\Models\Agent as AgentModel;
use App\Models\Run;
use App\Models\Tool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class WorkspaceAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public AgentModel $agentModel,
        public ?Run $run = null,
    ) {}

    public function instructions(): string
    {
        return $this->agentModel->instructions;
    }

    /**
     * @return iterable<int, DynamicTool>
     */
    public function tools(): iterable
    {
        return $this->agentModel->tools()
            ->where('is_active', true)
            ->get()
            ->map(fn (Tool $tool): DynamicTool => new DynamicTool($tool, $this->run))
            ->all();
    }
}
