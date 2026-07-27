<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowShareRequest;
use App\Http\Resources\V1\WorkflowShareResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\WorkflowShare;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowShareController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            WorkflowShareResource::collection($workflow->shares()->latest()->get()),
        );
    }

    public function store(StoreWorkflowShareRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        $share = $workflow->shares()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'token' => Str::random(40),
        ]);

        return ApiResponse::created(new WorkflowShareResource($share), 'Share link created.');
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, WorkflowShare $share): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);
        abort_if($share->workflow_id !== $workflow->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $share->delete();

        return ApiResponse::success(null, 'Share link revoked.');
    }

    private function ensureWorkflowBelongsToWorkspace(Workspace $workspace, Workflow $workflow): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
    }
}
