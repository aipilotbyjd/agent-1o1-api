<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workflows\TagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncWorkflowTagsController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowManage);

        $validated = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $workflow->tags()->sync($validated['tag_ids']);

        return ApiResponse::success(
            TagResource::collection($workflow->tags()->orderBy('name')->get()),
            'Workflow tags updated.',
        );
    }
}
