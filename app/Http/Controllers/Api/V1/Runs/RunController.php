<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Runs\RunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::RunView);

        $runs = $workspace->runs()
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return ApiResponse::success(RunResource::collection($runs)->response()->getData(true));
    }

    public function show(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);

        $this->requirePermission(Permission::RunView);

        return ApiResponse::success(new RunResource($run->load('steps')));
    }

    public function cancel(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);

        $canCancel = $run->triggered_by === $request->user()->id
            || $request->user()->can(Permission::RunApprovalReview->value);

        if (! $canCancel) {
            return ApiResponse::forbidden();
        }

        if ($run->status->isTerminal()) {
            return ApiResponse::error('This run has already finished.');
        }

        $run->cancel();

        return ApiResponse::success(new RunResource($run->load('steps')), 'Run cancelled.');
    }
}
