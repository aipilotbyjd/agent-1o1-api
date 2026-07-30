<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Agents\AgentResource;
use App\Http\Resources\V1\Agents\AgentVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentVersion;
use App\Models\Nodes\Node;
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

        // Only re-attach nodes still visible to this workspace, keeping each one's
        // bound config and exposed fields as they were when the version was taken.
        $attachments = collect($snapshot['nodes'] ?? [])->keyBy('node_id');

        $visibleNodeIds = Node::query()
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id))
            ->whereIn('id', $attachments->keys())
            ->pluck('id');

        $agent->nodes()->sync(
            $visibleNodeIds->mapWithKeys(fn (int $nodeId): array => [$nodeId => [
                'config' => $attachments[$nodeId]['config'] ?? null,
                'exposed_fields' => $attachments[$nodeId]['exposed_fields'] ?? null,
            ]])->all(),
        );

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
