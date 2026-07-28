<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreRunReplayPackRequest;
use App\Http\Resources\V1\Runs\RunReplayPackResource;
use App\Http\Resources\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\RunReplayPack;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunReplayPackController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::RunReplayPackView);

        return ApiResponse::success(
            RunReplayPackResource::collection($workflow->replayPacks()->latest()->get()),
        );
    }

    public function store(StoreRunReplayPackRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $pack = $workflow->replayPacks()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new RunReplayPackResource($pack), 'Replay pack created.');
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow, RunReplayPack $replayPack): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($replayPack->workflow_id !== $workflow->id, 404);

        $this->requirePermission(Permission::RunReplayPackView);

        return ApiResponse::success(new RunReplayPackResource($replayPack));
    }

    public function replay(Request $request, Workspace $workspace, Workflow $workflow, RunReplayPack $replayPack, WorkflowRunner $runner): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($replayPack->workflow_id !== $workflow->id, 404);

        $this->requirePermission(Permission::RunReplayPackManage);

        $run = $runner->replay($replayPack, $request->user());

        return ApiResponse::created(new RunResource($run->load('steps')), 'Replay started.');
    }
}
