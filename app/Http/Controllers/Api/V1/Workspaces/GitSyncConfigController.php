<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workspaces\StoreGitSyncConfigRequest;
use App\Http\Requests\Api\V1\Workspaces\UpdateGitSyncConfigRequest;
use App\Http\Resources\V1\GitSyncConfigResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\SyncWorkflowsToGit;
use App\Models\GitSyncConfig;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitSyncConfigController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

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
        $this->ensureConfigBelongsToWorkspace($workspace, $gitSyncConfig);

        $gitSyncConfig->update($request->validated());

        return ApiResponse::success(new GitSyncConfigResource($gitSyncConfig), 'Git sync config updated.');
    }

    public function destroy(Request $request, Workspace $workspace, GitSyncConfig $gitSyncConfig): JsonResponse
    {
        $this->ensureConfigBelongsToWorkspace($workspace, $gitSyncConfig);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $gitSyncConfig->delete();

        return ApiResponse::success(null, 'Git sync config deleted.');
    }

    public function sync(Request $request, Workspace $workspace, GitSyncConfig $gitSyncConfig): JsonResponse
    {
        $this->ensureConfigBelongsToWorkspace($workspace, $gitSyncConfig);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        SyncWorkflowsToGit::dispatch($gitSyncConfig->id);

        return ApiResponse::success(null, 'Sync queued.');
    }

    private function ensureConfigBelongsToWorkspace(Workspace $workspace, GitSyncConfig $gitSyncConfig): void
    {
        abort_if($gitSyncConfig->workspace_id !== $workspace->id, 404);
    }
}
