<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowContractSnapshotRequest;
use App\Http\Resources\V1\Workflows\WorkflowContractSnapshotResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowContractSnapshot;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ContractGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowContractSnapshotController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowContractSnapshotView);

        return ApiResponse::success(
            WorkflowContractSnapshotResource::collection($workflow->contractSnapshots()->latest()->get()),
        );
    }

    public function store(StoreWorkflowContractSnapshotRequest $request, Workspace $workspace, Workflow $workflow, ContractGenerator $generator): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $validated = $request->validated();

        $snapshot = $workflow->contractSnapshots()->create([
            ...$validated,
            'node_signature' => $validated['node_signature'] ?? $generator->signatureFor($workflow),
            'version_id' => $validated['version_id'] ?? $workflow->current_version_id,
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new WorkflowContractSnapshotResource($snapshot), 'Contract snapshot created.');
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($snapshot->workflow_id !== $workflow->id, 404);

        $this->requirePermission(Permission::WorkflowContractSnapshotView);

        return ApiResponse::success(new WorkflowContractSnapshotResource($snapshot));
    }
}
