<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreStickyNoteRequest;
use App\Http\Resources\V1\Workflows\StickyNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\StickyNote;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StickyNoteController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $this->requirePermission(Permission::WorkflowView);

        return ApiResponse::success(
            StickyNoteResource::collection($workflow->stickyNotes()->orderBy('z_index')->get()),
        );
    }

    public function store(StoreStickyNoteRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        $note = $workflow->stickyNotes()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new StickyNoteResource($note), 'Sticky note created.');
    }

    public function update(StoreStickyNoteRequest $request, Workspace $workspace, Workflow $workflow, StickyNote $stickyNote): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($stickyNote->workflow_id !== $workflow->id, 404);

        $stickyNote->update($request->validated());

        return ApiResponse::success(new StickyNoteResource($stickyNote->fresh()), 'Sticky note updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, StickyNote $stickyNote): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        abort_if($stickyNote->workflow_id !== $workflow->id, 404);

        $this->requirePermission(Permission::WorkflowManage);

        $stickyNote->delete();

        return ApiResponse::success(null, 'Sticky note deleted.');
    }
}
