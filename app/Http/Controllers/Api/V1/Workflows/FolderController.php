<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreFolderRequest;
use App\Http\Requests\Api\V1\Workflows\UpdateFolderRequest;
use App\Http\Resources\V1\Workflows\FolderResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Folder;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowView);

        $folders = $workspace->folders()
            ->whereNull('parent_id')
            ->with('children')
            ->withCount('workflows')
            ->orderBy('position')
            ->get();

        return ApiResponse::success(FolderResource::collection($folders));
    }

    public function store(StoreFolderRequest $request, Workspace $workspace): JsonResponse
    {
        $folder = $workspace->folders()->create($request->validated());

        return ApiResponse::created(new FolderResource($folder), 'Folder created.');
    }

    public function update(UpdateFolderRequest $request, Workspace $workspace, Folder $folder): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $folder);

        $folder->update($request->validated());

        return ApiResponse::success(new FolderResource($folder->fresh()), 'Folder updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Folder $folder): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $folder);

        $this->requirePermission(Permission::WorkflowManage);

        // Workflows inside fall back to no folder (FK is nullOnDelete).
        $folder->delete();

        return ApiResponse::success(null, 'Folder deleted.');
    }

    public function moveWorkflows(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowManage);

        $validated = $request->validate([
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => ['integer'],
            'folder_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('folders', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $workspace->workflows()
            ->whereIn('id', $validated['workflow_ids'])
            ->update(['folder_id' => $validated['folder_id']]);

        return ApiResponse::success(null, 'Workflows moved.');
    }
}
