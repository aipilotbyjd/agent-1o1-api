<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreWorkflowBuilderMessageRequest;
use App\Http\Resources\V1\WorkflowBuilderMessageResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessWorkflowBuilderMessage;
use App\Models\WorkflowBuilderSession;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowBuilderMessageController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkflowBuilderSession $session): JsonResponse
    {
        $this->ensureSessionBelongsToWorkspace($workspace, $session);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            WorkflowBuilderMessageResource::collection(
                $session->messages()->orderBy('created_at')->paginate(25),
            ),
        );
    }

    /**
     * Persists the user's message as 'pending', then queues ProcessWorkflowBuilderMessage
     * to drive the builder agent and produce the assistant reply plus any resulting draft version.
     */
    public function store(StoreWorkflowBuilderMessageRequest $request, Workspace $workspace, WorkflowBuilderSession $session): JsonResponse
    {
        $this->ensureSessionBelongsToWorkspace($workspace, $session);

        $message = $session->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
            'processing_status' => 'pending',
        ]);

        $session->update(['last_activity_at' => now()]);

        ProcessWorkflowBuilderMessage::dispatch($message->id);

        return ApiResponse::created(new WorkflowBuilderMessageResource($message), 'Message queued.');
    }

    private function ensureSessionBelongsToWorkspace(Workspace $workspace, WorkflowBuilderSession $session): void
    {
        abort_if($session->workspace_id !== $workspace->id, 404);
    }
}
