<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentSkillReferenceRequest;
use App\Http\Requests\Api\V1\Agents\UpdateAgentSkillReferenceRequest;
use App\Http\Resources\V1\Agents\AgentSkillReferenceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\AgentSkill;
use App\Models\Agents\AgentSkillReference;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSkillReferenceController extends Controller
{
    public function index(Request $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $this->requirePermission(Permission::AgentSkillReferenceView);

        return ApiResponse::success(
            AgentSkillReferenceResource::collection($skill->references()->orderBy('sort_order')->get()),
        );
    }

    public function store(StoreAgentSkillReferenceRequest $request, Workspace $workspace, AgentSkill $skill): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);

        $reference = $skill->references()->create($request->validated());

        return ApiResponse::created(new AgentSkillReferenceResource($reference), 'Reference created.');
    }

    public function update(UpdateAgentSkillReferenceRequest $request, Workspace $workspace, AgentSkill $skill, AgentSkillReference $reference): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureReferenceBelongsToSkill($skill, $reference);

        $reference->update($request->validated());

        return ApiResponse::success(new AgentSkillReferenceResource($reference), 'Reference updated.');
    }

    public function destroy(Request $request, Workspace $workspace, AgentSkill $skill, AgentSkillReference $reference): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $skill);
        $this->ensureReferenceBelongsToSkill($skill, $reference);

        $this->requirePermission(Permission::AgentSkillReferenceManage);

        $reference->delete();

        return ApiResponse::success(null, 'Reference deleted.');
    }

    private function ensureReferenceBelongsToSkill(AgentSkill $skill, AgentSkillReference $reference): void
    {
        abort_if($reference->skill_id !== $skill->id, 404);
    }
}
