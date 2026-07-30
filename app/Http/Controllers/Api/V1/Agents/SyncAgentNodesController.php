<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Nodes\NodeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncAgentNodesController extends Controller
{
    /**
     * Replace the set of nodes an agent may call as tools.
     *
     * Each attachment may bind config values the model must not choose (a credential, a
     * fixed channel) and narrow the fields it is offered, so one catalog node can be
     * handed to different agents on different terms.
     */
    public function __invoke(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);

        $this->requirePermission(Permission::AgentNodesSync);

        $validated = $request->validate([
            'nodes' => ['present', 'array'],
            'nodes.*.node_id' => [
                'required',
                'integer',
                // Builtin catalog rows are shared, so a node either belongs to this
                // workspace or belongs to nobody.
                Rule::exists('nodes', 'id')->where(
                    fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id),
                ),
            ],
            'nodes.*.config' => ['sometimes', 'nullable', 'array'],
            'nodes.*.exposed_fields' => ['sometimes', 'nullable', 'array'],
            'nodes.*.exposed_fields.*' => ['string'],
        ]);

        $attachments = collect($validated['nodes'])
            ->keyBy('node_id')
            ->map(fn (array $attachment): array => [
                'config' => $attachment['config'] ?? null,
                'exposed_fields' => $attachment['exposed_fields'] ?? null,
            ])
            ->all();

        $changes = $agent->nodes()->sync($attachments);

        if ($changes['attached'] !== [] || $changes['detached'] !== [] || $changes['updated'] !== []) {
            $agent->snapshotVersion($request->user());
        }

        return ApiResponse::success(
            NodeResource::collection($agent->nodes()->get()),
            'Agent nodes updated.',
        );
    }
}
