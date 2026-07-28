<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Triggers\TriggerEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\Trigger;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerEventController extends Controller
{
    /**
     * $parent consumes the {agent}/{workflow} route segment this endpoint is
     * nested under — unused here since ownership is checked via $trigger directly.
     */
    public function index(Request $request, Workspace $workspace, mixed $parent, Trigger $trigger): JsonResponse
    {
        abort_if($trigger->workspace_id !== $workspace->id, 404);

        $this->requirePermission(Permission::TriggerEventView);

        return ApiResponse::success(
            TriggerEventResource::collection(
                $trigger->triggerEvents()->latest()->paginate(25),
            ),
        );
    }
}
