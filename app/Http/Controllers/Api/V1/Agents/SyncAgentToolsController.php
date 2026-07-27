<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ToolResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncAgentToolsController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'tool_ids' => ['present', 'array'],
            'tool_ids.*' => ['integer', Rule::exists('tools', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $changes = $agent->tools()->sync($validated['tool_ids']);

        if ($changes['attached'] !== [] || $changes['detached'] !== []) {
            $agent->snapshotVersion($request->user());
        }

        return ApiResponse::success(
            ToolResource::collection($agent->tools()->get()),
            'Agent tools updated.',
        );
    }
}
