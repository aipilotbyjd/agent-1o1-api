<?php

namespace App\Http\Controllers\Api\V1\Nodes;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Nodes\StoreNodeRequest;
use App\Http\Requests\Api\V1\Nodes\UpdateNodeRequest;
use App\Http\Resources\V1\Nodes\NodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Nodes\Node;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    /**
     * List the builtin catalog plus this workspace's custom nodes.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::NodeView);

        $nodes = Node::query()
            ->with('category')
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id))
            ->when($request->query('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->get();

        return ApiResponse::success(NodeResource::collection($nodes));
    }

    public function show(Request $request, Workspace $workspace, Node $node): JsonResponse
    {
        $this->ensureNodeVisibleToWorkspace($workspace, $node);

        $this->requirePermission(Permission::NodeView);

        return ApiResponse::success(new NodeResource($node->load('category')));
    }

    public function store(StoreNodeRequest $request, Workspace $workspace): JsonResponse
    {
        $node = $workspace->nodes()->create([
            ...$request->validated(),
            'type' => $this->uniqueType($workspace, $request->validated('name')),
            'is_custom' => true,
            'is_active' => $request->validated('is_active') ?? true,
        ]);

        return ApiResponse::created(new NodeResource($node->load('category')), 'Node created.');
    }

    public function update(UpdateNodeRequest $request, Workspace $workspace, Node $node): JsonResponse
    {
        $this->ensureNodeVisibleToWorkspace($workspace, $node);
        abort_unless($node->is_custom, 403, 'Builtin catalog nodes cannot be modified.');

        $node->update($request->validated());

        return ApiResponse::success(new NodeResource($node->load('category')), 'Node updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Node $node): JsonResponse
    {
        $this->ensureNodeVisibleToWorkspace($workspace, $node);
        abort_unless($node->is_custom, 403, 'Builtin catalog nodes cannot be deleted.');

        $this->requirePermission(Permission::NodeManage);

        $node->delete();

        return ApiResponse::success(null, 'Node deleted.');
    }

    private function uniqueType(Workspace $workspace, string $name): string
    {
        $base = 'custom_'.$workspace->id.'_'.Str::slug($name, '_');
        $type = $base;
        $suffix = 1;

        while (Node::query()->where('type', $type)->exists()) {
            $type = $base.'_'.++$suffix;
        }

        return $type;
    }

    private function ensureNodeVisibleToWorkspace(Workspace $workspace, Node $node): void
    {
        abort_if($node->workspace_id !== null && $node->workspace_id !== $workspace->id, 404);
    }
}
