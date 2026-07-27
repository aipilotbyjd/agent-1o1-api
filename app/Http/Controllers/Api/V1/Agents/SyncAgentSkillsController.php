<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgentSkillResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncAgentSkillsController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'skill_ids' => ['present', 'array'],
            'skill_ids.*' => ['integer', Rule::exists('agent_skills', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $agent->skills()->sync($validated['skill_ids']);

        return ApiResponse::success(
            AgentSkillResource::collection($agent->skills()->get()),
            'Agent skills updated.',
        );
    }
}
