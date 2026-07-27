<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\V1\Workflows\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowView);

        return ApiResponse::success(
            WorkflowResource::collection($workspace->workflows()->latest()->get()),
        );
    }

    public function store(StoreWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        $workflow = $workspace->workflows()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($workspace, $request->validated('name')),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new WorkflowResource($workflow), 'Workflow created.');
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowView);

        return ApiResponse::success(new WorkflowResource($workflow->load(['steps', 'edges.fromStep', 'edges.toStep'])));
    }

    public function update(UpdateWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $workflow->update($request->validated());

        return ApiResponse::success(new WorkflowResource($workflow), 'Workflow updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowManage);

        $workflow->delete();

        return ApiResponse::success(null, 'Workflow deleted.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($workspace->workflows()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
