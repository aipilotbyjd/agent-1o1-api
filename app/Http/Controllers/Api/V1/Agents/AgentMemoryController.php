<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentMemoryRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentMemoryRequest;
use App\Http\Resources\V1\Agents\AgentMemoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMemory;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMemoryController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentMemoryView);

        return ApiResponse::success(
            AgentMemoryResource::collection($agent->memories()->latest()->get()),
        );
    }

    public function store(StoreAgentMemoryRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $memory = $agent->memories()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
        ]);

        return ApiResponse::created(new AgentMemoryResource($memory), 'Memory created.');
    }

    public function update(UpdateAgentMemoryRequest $request, Workspace $workspace, Agent $agent, AgentMemory $memory): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureMemoryBelongsToAgent($agent, $memory);

        $memory->update($request->validated());

        return ApiResponse::success(new AgentMemoryResource($memory), 'Memory updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentMemory $memory): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureMemoryBelongsToAgent($agent, $memory);

        $this->requirePermission(Permission::AgentMemoryManage);

        $memory->delete();

        return ApiResponse::success(null, 'Memory deleted.');
    }

    private function ensureMemoryBelongsToAgent(Agent $agent, AgentMemory $memory): void
    {
        abort_if($memory->agent_id !== $agent->id, 404);
    }
}
