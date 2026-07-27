<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentKnowledgeRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentKnowledgeRequest;
use App\Http\Resources\V1\AgentKnowledgeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\AgentKnowledge;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentKnowledgeController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            AgentKnowledgeResource::collection($agent->knowledge()->orderBy('sort_order')->get()),
        );
    }

    public function store(StoreAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        $knowledge = $agent->knowledge()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
        ]);

        return ApiResponse::created(new AgentKnowledgeResource($knowledge), 'Knowledge entry created.');
    }

    public function update(UpdateAgentKnowledgeRequest $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureKnowledgeBelongsToAgent($agent, $knowledge);

        $knowledge->update($request->validated());

        return ApiResponse::success(new AgentKnowledgeResource($knowledge), 'Knowledge entry updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentKnowledge $knowledge): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureKnowledgeBelongsToAgent($agent, $knowledge);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $knowledge->delete();

        return ApiResponse::success(null, 'Knowledge entry deleted.');
    }

    private function ensureAgentBelongsToWorkspace(Workspace $workspace, Agent $agent): void
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);
    }

    private function ensureKnowledgeBelongsToAgent(Agent $agent, AgentKnowledge $knowledge): void
    {
        abort_if($knowledge->agent_id !== $agent->id, 404);
    }
}
