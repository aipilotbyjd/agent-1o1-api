<?php

namespace App\Http\Controllers\Api\V1\Variables;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Variables\StoreVariableRequest;
use App\Http\Requests\Api\V1\Variables\UpdateVariableRequest;
use App\Http\Resources\V1\Variables\VariableResource;
use App\Http\Responses\ApiResponse;
use App\Models\Variable;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariableController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::VariableView);

        return ApiResponse::success(
            VariableResource::collection($workspace->variables()->orderBy('key')->get()),
        );
    }

    public function store(StoreVariableRequest $request, Workspace $workspace): JsonResponse
    {
        $variable = $workspace->variables()->create([
            ...$request->validated(),
            'is_secret' => $request->validated('is_secret') ?? false,
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(new VariableResource($variable), 'Variable created.');
    }

    public function update(UpdateVariableRequest $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $variable);

        $variable->update($request->validated());

        return ApiResponse::success(new VariableResource($variable), 'Variable updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $variable);

        $this->requirePermission(Permission::VariableManage);

        $variable->delete();

        return ApiResponse::success(null, 'Variable deleted.');
    }
}
