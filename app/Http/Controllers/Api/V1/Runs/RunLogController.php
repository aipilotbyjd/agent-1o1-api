<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Runs\RunLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunLogController extends Controller
{
    public function index(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        abort_if($run->workspace_id !== $workspace->id, 404);

        $this->requirePermission(Permission::RunLogView);

        return ApiResponse::success(
            RunLogResource::collection($run->logs()->orderBy('logged_at')->paginate(25)),
        );
    }
}
