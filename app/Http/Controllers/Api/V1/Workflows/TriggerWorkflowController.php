<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerWorkflowController extends Controller
{
    public function __construct(public WorkflowRunner $runner) {}

    public function __invoke(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        if ($workflow->status !== 'published') {
            return ApiResponse::error('Only published workflows can be triggered.');
        }

        $validated = $request->validate([
            'input' => ['sometimes', 'array'],
            'environment_id' => ['sometimes', 'integer', 'exists:workspace_environments,id'],
        ]);

        $environment = isset($validated['environment_id'])
            ? WorkspaceEnvironment::query()->where('workspace_id', $workspace->id)->find($validated['environment_id'])
            : WorkspaceEnvironment::query()->where('workspace_id', $workspace->id)->where('is_default', true)->first();

        $run = $this->runner->start($workflow, $request->user(), $validated['input'] ?? [], environment: $environment);

        return ApiResponse::created(new RunResource($run->load('steps')), 'Workflow run started.');
    }
}
