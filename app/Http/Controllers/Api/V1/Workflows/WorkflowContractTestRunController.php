<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowContractTestRunRequest;
use App\Http\Resources\V1\Workflows\WorkflowContractTestRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowContractSnapshot;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ContractGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowContractTestRunController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): JsonResponse
    {
        $this->ensureSnapshotBelongsToWorkflow($workspace, $workflow, $snapshot);

        $this->requirePermission(Permission::WorkflowContractTestRunView);

        return ApiResponse::success(
            WorkflowContractTestRunResource::collection($snapshot->testRuns()->latest()->get()),
        );
    }

    public function store(StoreWorkflowContractTestRunRequest $request, Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot, ContractGenerator $generator): JsonResponse
    {
        $this->ensureSnapshotBelongsToWorkflow($workspace, $workflow, $snapshot);

        $results = $generator->diff($snapshot->node_signature, $generator->signatureFor($workflow));

        $testRun = $snapshot->testRuns()->create([
            'status' => $results['drifted'] ? 'failed' : 'passed',
            'results' => $results,
        ]);

        return ApiResponse::created(new WorkflowContractTestRunResource($testRun), 'Contract test run recorded.');
    }

    private function ensureSnapshotBelongsToWorkflow(Workspace $workspace, Workflow $workflow, WorkflowContractSnapshot $snapshot): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
        abort_if($snapshot->workflow_id !== $workflow->id, 404);
    }
}
