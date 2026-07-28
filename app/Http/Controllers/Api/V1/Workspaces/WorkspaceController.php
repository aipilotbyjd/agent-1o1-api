<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateWorkspaceAvatarRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateWorkspaceRequest;
use App\Http\Resources\V1\Workspaces\WorkspaceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()->workspaces()->with('owner')->get();

        return ApiResponse::success(WorkspaceResource::collection($workspaces));
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->create($request->user(), $request->validated());

        return ApiResponse::created(new WorkspaceResource($workspace->load('owner')), 'Workspace created successfully');
    }

    public function show(Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkspaceView);

        return ApiResponse::success(new WorkspaceResource($workspace->load('owner')));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceService->update($workspace, $request->validated());

        return ApiResponse::success(new WorkspaceResource($workspace->load('owner')), 'Workspace updated successfully');
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkspaceDelete);

        $this->workspaceService->delete($workspace);

        return ApiResponse::success(null, 'Workspace deleted successfully');
    }

    public function updateAvatar(UpdateWorkspaceAvatarRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaceService->updateAvatar($workspace, $request->file('avatar'));

        return ApiResponse::success(new WorkspaceResource($workspace->load('owner')), 'Workspace avatar updated successfully');
    }
}
