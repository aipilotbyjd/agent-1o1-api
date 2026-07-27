<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\RunStepStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\Workspace;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunApprovalController extends Controller
{
    public function __construct(public WorkflowRunner $runner) {}

    public function approve(Request $request, Workspace $workspace, Run $run, RunStep $step): JsonResponse
    {
        return $this->resolve($request, $workspace, $run, $step, approved: true);
    }

    public function reject(Request $request, Workspace $workspace, Run $run, RunStep $step): JsonResponse
    {
        return $this->resolve($request, $workspace, $run, $step, approved: false);
    }

    private function resolve(Request $request, Workspace $workspace, Run $run, RunStep $step, bool $approved): JsonResponse
    {
        abort_if($run->workspace_id !== $workspace->id, 404);
        abort_if($step->run_id !== $run->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        if ($step->status !== RunStepStatus::AwaitingApproval) {
            return ApiResponse::error('This step is not awaiting approval.');
        }

        $this->runner->resolveApproval($run, $step, $approved, $request->user());

        return ApiResponse::success(
            new RunResource($run->fresh()->load('steps')),
            $approved ? 'Step approved.' : 'Step rejected.',
        );
    }
}
