<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentSkillRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentSkillRequest;
use App\Http\Resources\V1\AgentSkillResource;
use App\Http\Responses\ApiResponse;
use App\Models\AgentSkill;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentSkillController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            AgentSkillResource::collection($workspace->agentSkills()->orderBy('name')->get()),
        );
    }

    public function store(StoreAgentSkillRequest $request, Workspace $workspace): JsonResponse
    {
        $skill = $workspace->agentSkills()->create([
            ...$request->validated(),
            'slug' => $request->validated('slug') ?? Str::slug($request->validated('name')),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new AgentSkillResource($skill), 'Skill created.');
    }

    public function show(Request $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureSkillBelongsToWorkspace($workspace, $skill);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(new AgentSkillResource($skill));
    }

    public function update(UpdateAgentSkillRequest $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureSkillBelongsToWorkspace($workspace, $skill);

        $skill->update($request->validated());

        return ApiResponse::success(new AgentSkillResource($skill), 'Skill updated.');
    }

    public function destroy(Request $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureSkillBelongsToWorkspace($workspace, $skill);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $skill->delete();

        return ApiResponse::success(null, 'Skill deleted.');
    }

    private function ensureSkillBelongsToWorkspace(Workspace $workspace, AgentSkill $skill): void
    {
        abort_if($skill->workspace_id !== $workspace->id, 404);
    }
}
