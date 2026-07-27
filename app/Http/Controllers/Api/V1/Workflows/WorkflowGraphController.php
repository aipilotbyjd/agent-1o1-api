<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\SaveWorkflowGraphRequest;
use App\Http\Resources\V1\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class WorkflowGraphController extends Controller
{
    public function __invoke(SaveWorkflowGraphRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);

        $workflow->replaceGraph($request->validated('steps'), $request->validated('edges'));

        return ApiResponse::success(
            new WorkflowResource($workflow->fresh()->load(['steps', 'edges.fromStep', 'edges.toStep', 'currentVersion'])),
            'Workflow graph saved.',
        );
    }
}
