<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowShareRequest;
use App\Http\Resources\V1\Workflows\WorkflowShareResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowShare;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowShareController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowShareView);

        return ApiResponse::success(
            WorkflowShareResource::collection($workflow->shares()->latest()->get()),
        );
    }

    public function store(StoreWorkflowShareRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

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
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($share->workflow_id !== $workflow->id, 404);

        $this->requirePermission(Permission::WorkflowShareManage);

        $share->delete();

        return ApiResponse::success(null, 'Share link revoked.');
    }
}
