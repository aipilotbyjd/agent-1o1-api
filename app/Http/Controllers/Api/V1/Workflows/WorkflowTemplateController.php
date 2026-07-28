<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Workflows\WorkflowResource;
use App\Http\Resources\V1\Workflows\WorkflowTemplateResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflows\WorkflowTemplate;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $templates = WorkflowTemplate::query()
            ->where('is_active', true)
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(WorkflowTemplateResource::collection($templates));
    }

    public function show(string $key): JsonResponse
    {
        $template = WorkflowTemplate::query()
            ->where('id', $key)
            ->orWhere('slug', $key)
            ->firstOrFail();

        return ApiResponse::success(new WorkflowTemplateResource($template));
    }

    public function instantiate(Request $request, Workspace $workspace, WorkflowTemplate $template): JsonResponse
    {
        $this->requirePermission(Permission::WorkflowTemplateUse);

        $workflow = $workspace->workflows()->create([
            'name' => $template->name,
            'slug' => $this->uniqueSlug($workspace, $template->name),
            'description' => $template->description,
            'created_by' => $request->user()->id,
        ]);

        $workflow->replaceGraph($template->graph['steps'] ?? [], $template->graph['edges'] ?? []);

        $template->increment('usage_count');

        return ApiResponse::created(new WorkflowResource($workflow->fresh()), 'Workflow created from template.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($workspace->workflows()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
