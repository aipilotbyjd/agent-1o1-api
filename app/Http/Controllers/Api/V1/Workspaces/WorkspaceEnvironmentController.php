<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreWorkspaceEnvironmentRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateWorkspaceEnvironmentRequest;
use App\Http\Resources\V1\WorkspaceEnvironmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceEnvironmentController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

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
        $this->ensureEnvironmentBelongsToWorkspace($workspace, $environment);

        $environment->update($request->validated());

        return ApiResponse::success(new WorkspaceEnvironmentResource($environment), 'Environment updated.');
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->ensureEnvironmentBelongsToWorkspace($workspace, $environment);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $environment->delete();

        return ApiResponse::success(null, 'Environment deleted.');
    }

    private function ensureEnvironmentBelongsToWorkspace(Workspace $workspace, WorkspaceEnvironment $environment): void
    {
        abort_if($environment->workspace_id !== $workspace->id, 404);
    }
}
