<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RunLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunLogController extends Controller
{
    public function index(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        abort_if($run->workspace_id !== $workspace->id, 404);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            RunLogResource::collection($run->logs()->orderBy('logged_at')->paginate(25)),
        );
    }
}
