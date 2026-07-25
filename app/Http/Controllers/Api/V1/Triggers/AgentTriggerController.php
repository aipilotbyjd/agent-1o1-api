<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Resources\V1\TriggerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\Trigger;
use App\Models\Workspace;
use App\Services\TriggerBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTriggerController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            TriggerResource::collection(
                Trigger::query()
                    ->where('triggerable_type', $agent->getMorphClass())
                    ->where('triggerable_id', $agent->id)
                    ->latest()
                    ->get(),
            ),
        );
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Agent $agent, TriggerBuilder $builder): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        $trigger = $builder->create($workspace, $agent, $request->user(), $request->validated());

        return ApiResponse::created(new TriggerResource($trigger->load('triggerType')), 'Trigger created.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        abort_if($trigger->triggerable_id !== $agent->id || $trigger->triggerable_type !== $agent->getMorphClass(), 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $trigger->delete();

        return ApiResponse::success(null, 'Trigger deleted.');
    }

    private function ensureAgentBelongsToWorkspace(Workspace $workspace, Agent $agent): void
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);
    }
}
