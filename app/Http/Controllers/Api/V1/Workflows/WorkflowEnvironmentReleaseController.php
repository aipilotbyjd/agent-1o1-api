<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowEnvironmentReleaseRequest;
use App\Http\Resources\V1\Workflows\WorkflowEnvironmentReleaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowEnvironmentReleaseController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowEnvironmentReleaseView);

        return ApiResponse::success(
            WorkflowEnvironmentReleaseResource::collection($workflow->environmentReleases()->latest('released_at')->get()),
        );
    }

    public function store(StoreWorkflowEnvironmentReleaseRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $release = $workflow->environmentReleases()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'released_by' => $request->user()->id,
            'released_at' => now(),
        ]);

        return ApiResponse::created(new WorkflowEnvironmentReleaseResource($release), 'Release recorded.');
    }
}
