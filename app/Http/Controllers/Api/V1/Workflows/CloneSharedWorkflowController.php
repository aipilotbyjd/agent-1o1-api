<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkflowResource;
use App\Http\Responses\ApiResponse;
use App\Models\WorkflowShare;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clones a shared workflow's published graph into a *different* workspace than the one
 * that shared it — the target workspace is the one in the URL, gated on that workspace's
 * own membership, not the original workflow's.
 */
class CloneSharedWorkflowController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, string $token): JsonResponse
    {
        $share = WorkflowShare::query()->where('token', $token)->firstOrFail();

        if ($share->isExpired()) {
            return ApiResponse::error('This share link has expired.', Response::HTTP_GONE);
        }

        if (! $share->allow_clone) {
            return ApiResponse::forbidden('This share does not allow cloning.');
        }

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        $source = $share->workflow;

        $clone = $workspace->workflows()->create([
            'name' => "{$source->name} (Copy)",
            'slug' => $this->uniqueSlug($workspace, $source->name),
            'description' => $source->description,
            'created_by' => $request->user()->id,
        ]);

        $graph = $source->currentVersion?->graph ?? ['steps' => [], 'edges' => []];
        $clone->replaceGraph($graph['steps'] ?? [], $graph['edges'] ?? []);

        $share->recordView();

        return ApiResponse::created(new WorkflowResource($clone->fresh()), 'Workflow cloned.');
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
