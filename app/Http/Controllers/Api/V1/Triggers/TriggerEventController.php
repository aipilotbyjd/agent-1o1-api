<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TriggerEventResource;
use App\Http\Responses\ApiResponse;
use App\Models\Trigger;
use App\Models\Workspace;
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

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            TriggerEventResource::collection(
                $trigger->triggerEvents()->latest()->paginate(25),
            ),
        );
    }
}
