<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishWorkflowController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);

        $this->requirePermission(Permission::WorkflowPublish);

        if ($workflow->steps()->doesntExist()) {
            return ApiResponse::error('A workflow needs at least one step before it can be published.');
        }

        $validated = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $workflow->publishVersion($request->user(), $validated['notes'] ?? null);

        return ApiResponse::success(
            new WorkflowResource($workflow->fresh()->load('currentVersion')),
            'Workflow published.',
        );
    }
}
