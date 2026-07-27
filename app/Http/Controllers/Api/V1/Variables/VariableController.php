<?php

namespace App\Http\Controllers\Api\V1\Variables;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Variables\StoreVariableRequest;
use App\Http\Requests\Api\V1\Variables\UpdateVariableRequest;
use App\Http\Resources\V1\VariableResource;
use App\Http\Responses\ApiResponse;
use App\Models\Variable;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VariableController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

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
        $this->ensureVariableBelongsToWorkspace($workspace, $variable);

        $variable->update($request->validated());

        return ApiResponse::success(new VariableResource($variable), 'Variable updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Variable $variable): JsonResponse
    {
        $this->ensureVariableBelongsToWorkspace($workspace, $variable);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $variable->delete();

        return ApiResponse::success(null, 'Variable deleted.');
    }

    private function ensureVariableBelongsToWorkspace(Workspace $workspace, Variable $variable): void
    {
        abort_if($variable->workspace_id !== $workspace->id, 404);
    }
}
