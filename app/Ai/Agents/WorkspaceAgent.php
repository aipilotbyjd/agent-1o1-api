<?php

namespace App\Ai\Agents;

use App\Ai\Tools\NodeTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Agents\AgentKnowledge;
use App\Models\Agents\AgentMemory;
use App\Models\Agents\AgentSkill;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;

class WorkspaceAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public AgentModel $agentModel,
        public ?Run $run = null,
    ) {}

    /**
     * Prompt the agent with the provider and model its record configures.
     *
     * Every caller was passing `provider: $agent->provider, model: $agent->model` by
     * hand, which is not a choice any of them were making — it belongs to the agent.
     */
    public function ask(string $message): AgentResponse
    {
        return $this->prompt(
            $message,
            provider: $this->agentModel->provider,
            model: $this->agentModel->model,
        );
    }

    /**
     * The same, continuing an existing conversation on a user's behalf.
     */
    public function askAs(string $message, User $user, ?string $conversationId = null): AgentResponse
    {
        $pending = $conversationId !== null
            ? $this->continue($conversationId, as: $user)
            : $this->forUser($user);

        return $pending->prompt(
            $message,
            provider: $this->agentModel->provider,
            model: $this->agentModel->model,
        );
    }

    /**
     * Base instructions plus curated knowledge base entries and remembered facts, so the
     * agent has this context in every turn without needing to call a retrieval tool for it.
     */
    public function instructions(): string
    {
        $sections = [$this->agentModel->instructions];

        if ($knowledge = $this->knowledgeSection()) {
            $sections[] = $knowledge;
        }

        if ($memories = $this->memoriesSection()) {
            $sections[] = $memories;
        }

        if ($skills = $this->skillsSection()) {
            $sections[] = $skills;
        }

        return implode("\n\n", $sections);
    }

    private function knowledgeSection(): ?string
    {
        $entries = $this->agentModel->knowledge()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['title', 'content']);

        if ($entries->isEmpty()) {
            return null;
        }

        $body = $entries
            ->map(fn (AgentKnowledge $entry): string => "### {$entry->title}\n{$entry->content}")
            ->implode("\n\n");

        return "## Knowledge Base\n{$body}";
    }

    private function memoriesSection(): ?string
    {
        $query = $this->agentModel->memories()->orderBy('key');

        // Scope to the run's user when known, plus workspace-wide (user_id null) memories —
        // a memory tied to a specific user shouldn't leak into another user's conversation.
        $userId = $this->run?->triggered_by;
        $query->where(fn ($q) => $q->whereNull('user_id')->when($userId, fn ($q) => $q->orWhere('user_id', $userId)));

        $entries = $query->get(['key', 'value']);

        if ($entries->isEmpty()) {
            return null;
        }

        $body = $entries
            ->map(fn (AgentMemory $entry): string => "- {$entry->key}: {$entry->value}")
            ->implode("\n");

        return "## Things you remember\n{$body}";
    }

    /**
     * Skill instructions plus any reference material attached to them. Skill scripts are
     * intentionally not surfaced here — running them would need a sandboxed code-execution
     * tool that doesn't exist yet.
     */
    private function skillsSection(): ?string
    {
        $skills = $this->agentModel->skills()->with('references')->get();

        if ($skills->isEmpty()) {
            return null;
        }

        $body = $skills
            ->map(function (AgentSkill $skill): string {
                $section = "### {$skill->name}\n{$skill->instructions}";

                $references = $skill->references
                    ->map(fn ($reference): string => "- {$reference->title}: {$reference->content}")
                    ->implode("\n");

                return $references === '' ? $section : "{$section}\n{$references}";
            })
            ->implode("\n\n");

        return "## Skills\n{$body}";
    }

    /**
     * @return iterable<int, NodeTool|SearchKnowledgeTool>
     */
    public function tools(): iterable
    {
        $tools = $this->agentModel->nodes()
            ->where('is_active', true)
            ->get()
            ->map(fn (Node $node): NodeTool => new NodeTool($node, $this->agentModel, $this->run))
            ->all();

        // Only offer the tool when there's actually something to search — an empty
        // knowledge base would just tempt the model into calling it pointlessly.
        if (DocumentEmbedding::query()->where('workspace_id', $this->agentModel->workspace_id)->exists()) {
            $tools[] = new SearchKnowledgeTool($this->agentModel->workspace_id);
        }

        return $tools;
    }
}
