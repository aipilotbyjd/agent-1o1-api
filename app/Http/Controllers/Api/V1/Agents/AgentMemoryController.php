<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentMemoryRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentMemoryRequest;
use App\Http\Resources\V1\AgentMemoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\AgentMemory;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMemoryController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            AgentMemoryResource::collection($agent->memories()->latest()->get()),
        );
    }

    public function store(StoreAgentMemoryRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        $memory = $agent->memories()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
        ]);

        return ApiResponse::created(new AgentMemoryResource($memory), 'Memory created.');
    }

    public function update(UpdateAgentMemoryRequest $request, Workspace $workspace, Agent $agent, AgentMemory $memory): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureMemoryBelongsToAgent($agent, $memory);

        $memory->update($request->validated());

        return ApiResponse::success(new AgentMemoryResource($memory), 'Memory updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentMemory $memory): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureMemoryBelongsToAgent($agent, $memory);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $memory->delete();

        return ApiResponse::success(null, 'Memory deleted.');
    }

    private function ensureAgentBelongsToWorkspace(Workspace $workspace, Agent $agent): void
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);
    }

    private function ensureMemoryBelongsToAgent(Agent $agent, AgentMemory $memory): void
    {
        abort_if($memory->agent_id !== $agent->id, 404);
    }
}
