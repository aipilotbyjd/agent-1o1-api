<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\ReviewWorkflowApprovalRequest;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowApprovalRequest;
use App\Http\Resources\V1\WorkflowApprovalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\WorkflowApproval;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowApprovalController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            WorkflowApprovalResource::collection($workflow->approvals()->latest()->get()),
        );
    }

    public function store(StoreWorkflowApprovalRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

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
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);
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

    private function ensureWorkflowBelongsToWorkspace(Workspace $workspace, Workflow $workflow): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
    }
}
