<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentRequest;
use App\Http\Resources\V1\Agents\AgentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success(
            AgentResource::collection($workspace->agents()->latest()->get()),
        );
    }

    public function store(StoreAgentRequest $request, Workspace $workspace): JsonResponse
    {
        $agent = $workspace->agents()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($workspace, $request->validated('name')),
            'created_by' => $request->user()->id,
        ]);

        $agent->snapshotVersion($request->user());

        return ApiResponse::created(new AgentResource($agent), 'Agent created.');
    }

    public function show(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentView);

        return ApiResponse::success(new AgentResource($agent));
    }

    public function update(UpdateAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $agent->update($request->validated());

        // Only behavioral changes create a new version; renames are cosmetic.
        if ($agent->wasChanged(['instructions', 'provider', 'model', 'temperature', 'settings'])) {
            $agent->snapshotVersion($request->user());
        }

        return ApiResponse::success(new AgentResource($agent), 'Agent updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentManage);

        $agent->delete();

        return ApiResponse::success(null, 'Agent deleted.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($workspace->agents()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
