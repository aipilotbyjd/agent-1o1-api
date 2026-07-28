<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflows\StoreTagRequest;
use App\Http\Requests\Api\V1\Workflows\UpdateTagRequest;
use App\Http\Resources\V1\Workflows\TagResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\Tag;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowView);

        $tags = $workspace->tags()
            ->withCount('workflows')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(TagResource::collection($tags));
    }

    public function store(StoreTagRequest $request, Workspace $workspace): JsonResponse
    {
        if ($workspace->tags()->where('name', $request->validated('name'))->exists()) {
            return ApiResponse::validationError(['name' => ['A tag with this name already exists.']]);
        }

        $tag = $workspace->tags()->create($request->validated());

        return ApiResponse::created(new TagResource($tag), 'Tag created.');
    }

    public function update(UpdateTagRequest $request, Workspace $workspace, Tag $tag): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tag);

        $name = $request->validated('name');

        if ($name !== null && $workspace->tags()->where('name', $name)->whereKeyNot($tag->id)->exists()) {
            return ApiResponse::validationError(['name' => ['A tag with this name already exists.']]);
        }

        $tag->update($request->validated());

        return ApiResponse::success(new TagResource($tag->fresh()), 'Tag updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Tag $tag): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $tag);

        $this->requirePermission(Permission::WorkflowManage);

        $tag->delete();

        return ApiResponse::success(null, 'Tag deleted.');
    }
}
