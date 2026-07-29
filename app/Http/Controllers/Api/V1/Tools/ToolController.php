<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tools\StoreToolRequest;
use App\Http\Requests\Api\V1\Tools\UpdateToolRequest;
use App\Http\Resources\V1\Tools\ToolResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tool;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Nodes\Connectors\CustomHttpNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ToolController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::ToolView);

        return ApiResponse::success(
            ToolResource::collection($workspace->tools()->latest()->get()),
        );
    }

    public function store(StoreToolRequest $request, Workspace $workspace): JsonResponse
    {
        $tool = $workspace->tools()->create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($workspace, $request->validated('name')),
            'created_by' => $request->user()->id,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return ApiResponse::created(new ToolResource($tool), 'Tool created.');
    }

    public function show(Request $request, Workspace $workspace, Tool $tool): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tool);

        $this->requirePermission(Permission::ToolView);

        return ApiResponse::success(new ToolResource($tool));
    }

    public function update(UpdateToolRequest $request, Workspace $workspace, Tool $tool): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tool);

        $tool->update($request->validated());

        return ApiResponse::success(new ToolResource($tool), 'Tool updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Tool $tool): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tool);

        $this->requirePermission(Permission::ToolManage);

        $tool->delete();

        return ApiResponse::success(null, 'Tool deleted.');
    }

    public function test(Request $request, Workspace $workspace, Tool $tool, CustomHttpNode $node): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tool);

        $this->requirePermission(Permission::ToolManage);

        try {
            $result = $node->call($tool, $request->input('arguments', []));
        } catch (Throwable $exception) {
            return ApiResponse::error('Tool execution failed: '.$exception->getMessage());
        }

        return ApiResponse::success(['result' => $result], 'Tool executed.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name, '_');
        $slug = $base;
        $suffix = 1;

        while ($workspace->tools()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.++$suffix;
        }

        return $slug;
    }
}
