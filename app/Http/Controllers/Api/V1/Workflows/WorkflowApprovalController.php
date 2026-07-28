<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\ReviewWorkflowApprovalRequest;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowApprovalRequest;
use App\Http\Resources\V1\Workflows\WorkflowApprovalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowApproval;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowApprovalController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowApprovalView);

        return ApiResponse::success(
            WorkflowApprovalResource::collection($workflow->approvals()->latest()->get()),
        );
    }

    public function store(StoreWorkflowApprovalRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $approval = $workflow->approvals()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'requested_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new WorkflowApprovalResource($approval), 'Approval requested.');
    }

    public function approve(ReviewWorkflowApprovalRequest $request, Workspace $workspace, Workflow $workflow, WorkflowApproval $approval): JsonResponse
    {
        return $this->review($request, $workspace, $workflow, $approval, approved: true);
    }

    public function reject(ReviewWorkflowApprovalRequest $request, Workspace $workspace, Workflow $workflow, WorkflowApproval $approval): JsonResponse
    {
        return $this->review($request, $workspace, $workflow, $approval, approved: false);
    }

    private function review(ReviewWorkflowApprovalRequest $request, Workspace $workspace, Workflow $workflow, WorkflowApproval $approval, bool $approved): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($approval->workflow_id !== $workflow->id, 404);

        if ($approval->status !== 'pending') {
            return ApiResponse::error('This approval has already been reviewed.');
        }

        if ($approved) {
            $approval->approve($request->user(), $request->validated('notes'));
        } else {
            $approval->reject($request->user(), $request->validated('notes'));
        }

        return ApiResponse::success(
            new WorkflowApprovalResource($approval->fresh()),
            $approved ? 'Approval granted.' : 'Approval rejected.',
        );
    }
}
