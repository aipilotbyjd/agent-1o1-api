<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreGitSyncConfigRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateGitSyncConfigRequest;
use App\Http\Resources\V1\Workflows\GitSyncConfigResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Workflows\SyncWorkflowsToGit;
use App\Models\Workflows\GitSyncConfig;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitSyncConfigController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::GitSyncView);

        return ApiResponse::success(
            GitSyncConfigResource::collection($workspace->gitSyncConfigs()->get()),
        );
    }

    public function store(StoreGitSyncConfigRequest $request, Workspace $workspace): JsonResponse
    {
        $config = $workspace->gitSyncConfigs()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new GitSyncConfigResource($config), 'Git sync config created.');
    }

    public function update(UpdateGitSyncConfigRequest $request, Workspace $workspace, GitSyncConfig $gitSyncConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $gitSyncConfig);

        $gitSyncConfig->update($request->validated());

        return ApiResponse::success(new GitSyncConfigResource($gitSyncConfig), 'Git sync config updated.');
    }

    public function destroy(Request $request, Workspace $workspace, GitSyncConfig $gitSyncConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $gitSyncConfig);

        $this->requirePermission(Permission::GitSyncManage);

        $gitSyncConfig->delete();

        return ApiResponse::success(null, 'Git sync config deleted.');
    }

    public function sync(Request $request, Workspace $workspace, GitSyncConfig $gitSyncConfig): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $gitSyncConfig);

        $this->requirePermission(Permission::GitSyncManage);

        SyncWorkflowsToGit::dispatch($gitSyncConfig->id);

        return ApiResponse::success(null, 'Sync queued.');
    }
}
