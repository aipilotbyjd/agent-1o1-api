<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Agents\AgentResource;
use App\Http\Resources\V1\Agents\AgentVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentVersion;
use App\Models\Tool;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentVersionController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentVersionView);

        return ApiResponse::success(
            AgentVersionResource::collection($agent->versions()->orderByDesc('version')->get()),
        );
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, int $version): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentVersionView);

        return ApiResponse::success(new AgentVersionResource($this->findVersion($agent, $version)));
    }

    public function restore(Request $request, Workspace $workspace, Agent $agent, int $version): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentVersionManage);

        $snapshot = $this->findVersion($agent, $version)->snapshot;

        $agent->update([
            'instructions' => $snapshot['instructions'],
            'provider' => $snapshot['provider'],
            'model' => $snapshot['model'],
            'temperature' => $snapshot['temperature'],
            'settings' => $snapshot['settings'],
        ]);

        // Only re-attach tools that still exist in this workspace.
        $toolIds = Tool::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $snapshot['tool_ids'] ?? [])
            ->pluck('id');

        $agent->tools()->sync($toolIds);

        // Restore never rewrites history — it becomes the next version.
        $agent->snapshotVersion($request->user());

        return ApiResponse::success(
            new AgentResource($agent->fresh()),
            "Version {$version} restored as a new version.",
        );
    }

    private function findVersion(Agent $agent, int $version): AgentVersion
    {
        return $agent->versions()->where('version', $version)->firstOrFail();
    }
}
