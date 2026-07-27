<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreWorkspaceEnvironmentRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateWorkspaceEnvironmentRequest;
use App\Http\Resources\V1\Workspaces\WorkspaceEnvironmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceEnvironmentController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::EnvironmentView);

        return ApiResponse::success(
            WorkspaceEnvironmentResource::collection($workspace->environments()->orderBy('name')->get()),
        );
    }

    public function store(StoreWorkspaceEnvironmentRequest $request, Workspace $workspace): JsonResponse
    {
        $environment = $workspace->environments()->create($request->validated());

        return ApiResponse::created(new WorkspaceEnvironmentResource($environment), 'Environment created.');
    }

    public function update(UpdateWorkspaceEnvironmentRequest $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $environment);

        $environment->update($request->validated());

        return ApiResponse::success(new WorkspaceEnvironmentResource($environment), 'Environment updated.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $environment);

        $this->requirePermission(Permission::EnvironmentManage);

        $environment->delete();

        return ApiResponse::success(null, 'Environment deleted.');
    }
}
