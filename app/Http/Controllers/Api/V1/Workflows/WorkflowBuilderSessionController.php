<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowBuilderSessionRequest;
use App\Http\Resources\V1\Workflows\WorkflowBuilderSessionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowBuilderSession;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowBuilderSessionController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowBuilderUse);

        $sessions = $workspace->builderSessions()
            ->where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->get();

        return ApiResponse::success(WorkflowBuilderSessionResource::collection($sessions));
    }

    public function store(StoreWorkflowBuilderSessionRequest $request, Workspace $workspace): JsonResponse
    {
        $session = $workspace->builderSessions()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'draft_graph' => ['steps' => [], 'edges' => []],
            'last_activity_at' => now(),
        ]);

        return ApiResponse::created(new WorkflowBuilderSessionResource($session), 'Workflow builder session created.');
    }

    public function show(Request $request, Workspace $workspace, WorkflowBuilderSession $session): JsonResponse
    {
        $this->ensureSessionAccessible($request, $workspace, $session);

        return ApiResponse::success(new WorkflowBuilderSessionResource($session->loadCount('messages')));
    }

    public function destroy(Request $request, Workspace $workspace, WorkflowBuilderSession $session): JsonResponse
    {
        $this->ensureSessionAccessible($request, $workspace, $session);

        $session->delete();

        return ApiResponse::success(null, 'Workflow builder session deleted.');
    }

    /**
     * A member may only touch their own sessions; owner/admin may touch any session
     * in the workspace.
     */
    private function ensureSessionAccessible(Request $request, Workspace $workspace, WorkflowBuilderSession $session): void
    {
        abort_if($session->workspace_id !== $workspace->id, 404);

        $user = $request->user();

        abort_unless(
            $user->can(Permission::WorkflowManage->value) || $session->user_id === $user->id,
            403,
        );
    }
}
