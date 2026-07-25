<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        $runs = $workspace->runs()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success(RunResource::collection($runs)->response()->getData(true));
    }

    public function show(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureRunBelongsToWorkspace($workspace, $run);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(new RunResource($run->load('steps')));
    }

    public function cancel(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureRunBelongsToWorkspace($workspace, $run);

        $canCancel = $run->triggered_by === $request->user()->id
            || $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin');

        if (! $canCancel) {
            return ApiResponse::forbidden();
        }

        if ($run->status->isTerminal()) {
            return ApiResponse::error('This run has already finished.');
        }

        $run->cancel();

        return ApiResponse::success(new RunResource($run->load('steps')), 'Run cancelled.');
    }

    private function ensureRunBelongsToWorkspace(Workspace $workspace, Run $run): void
    {
        abort_if($run->workspace_id !== $workspace->id, 404);
    }
}
