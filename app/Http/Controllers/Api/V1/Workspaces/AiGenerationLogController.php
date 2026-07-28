<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workspaces\AiGenerationLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\AiGenerationLog;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiGenerationLogController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkspaceUsageView);

        $logs = AiGenerationLog::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return ApiResponse::success(AiGenerationLogResource::collection($logs)->response()->getData(true));
    }
}
