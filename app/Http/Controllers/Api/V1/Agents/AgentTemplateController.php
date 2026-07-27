<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgentResource;
use App\Http\Resources\V1\AgentTemplateResource;
use App\Http\Responses\ApiResponse;
use App\Models\AgentTemplate;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = AgentTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(AgentTemplateResource::collection($templates));
    }

    public function instantiate(Request $request, Workspace $workspace, AgentTemplate $template): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        $agent = $workspace->agents()->create([
            'name' => $template->name,
            'slug' => $this->uniqueSlug($workspace, $template->name),
            'description' => $template->description,
            'instructions' => $template->system_prompt ?? $template->instructions ?? '',
            'provider' => $template->llm_provider,
            'model' => $template->llm_model,
            'settings' => $template->llm_settings,
            'created_by' => $request->user()->id,
        ]);

        $agent->snapshotVersion($request->user());

        $template->increment('usage_count');

        return ApiResponse::created(new AgentResource($agent), 'Agent created from template.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while ($workspace->agents()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
