<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowEnvironmentReleaseRequest;
use App\Http\Resources\V1\WorkflowEnvironmentReleaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowEnvironmentReleaseController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            WorkflowEnvironmentReleaseResource::collection($workflow->environmentReleases()->latest('released_at')->get()),
        );
    }

    public function store(StoreWorkflowEnvironmentReleaseRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        $release = $workflow->environmentReleases()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'released_by' => $request->user()->id,
            'released_at' => now(),
        ]);

        return ApiResponse::created(new WorkflowEnvironmentReleaseResource($release), 'Release recorded.');
    }

    private function ensureWorkflowBelongsToWorkspace(Workspace $workspace, Workflow $workflow): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
    }
}
