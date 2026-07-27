<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentSkillScriptRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentSkillScriptRequest;
use App\Http\Resources\V1\Agents\AgentSkillScriptResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\AgentSkill;
use App\Models\Agents\AgentSkillScript;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSkillScriptController extends Controller
{
    public function index(Request $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $this->requirePermission(Permission::AgentSkillScriptView);

        return ApiResponse::success(
            AgentSkillScriptResource::collection($skill->scripts()->get()),
        );
    }

    public function store(StoreAgentSkillScriptRequest $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $script = $skill->scripts()->create($request->validated());

        return ApiResponse::created(new AgentSkillScriptResource($script), 'Script created.');
    }

    public function update(UpdateAgentSkillScriptRequest $request, Workspace $workspace, AgentSkill $skill, AgentSkillScript $script): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureScriptBelongsToSkill($skill, $script);

        $script->update($request->validated());

        return ApiResponse::success(new AgentSkillScriptResource($script), 'Script updated.');
    }

    public function destroy(Request $request, Workspace $workspace, AgentSkill $skill, AgentSkillScript $script): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureScriptBelongsToSkill($skill, $script);

        $this->requirePermission(Permission::AgentSkillScriptManage);

        $script->delete();

        return ApiResponse::success(null, 'Script deleted.');
    }

    private function ensureScriptBelongsToSkill(AgentSkill $skill, AgentSkillScript $script): void
    {
        abort_if($script->skill_id !== $skill->id, 404);
    }
}
