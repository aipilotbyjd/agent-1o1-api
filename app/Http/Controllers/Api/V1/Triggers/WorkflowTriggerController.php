<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Resources\V1\TriggerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Trigger;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\TriggerBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTriggerController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            TriggerResource::collection(
                Trigger::query()
                    ->where('triggerable_type', $workflow->getMorphClass())
                    ->where('triggerable_id', $workflow->id)
                    ->latest()
                    ->get(),
            ),
        );
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Workflow $workflow, TriggerBuilder $builder): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        $trigger = $builder->create($workspace, $workflow, $request->user(), $request->validated());

        return ApiResponse::created(new TriggerResource($trigger->load('triggerType')), 'Trigger created.');
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);
        abort_if($trigger->triggerable_id !== $workflow->id || $trigger->triggerable_type !== $workflow->getMorphClass(), 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $trigger->delete();

        return ApiResponse::success(null, 'Trigger deleted.');
    }

    private function ensureWorkflowBelongsToWorkspace(Workspace $workspace, Workflow $workflow): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
    }
}
