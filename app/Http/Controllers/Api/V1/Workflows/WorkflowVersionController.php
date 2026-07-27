<?php

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WorkflowResource;
use App\Http\Resources\V1\WorkflowVersionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowVersionController extends Controller
{
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            WorkflowVersionResource::collection(
                $workflow->versions()->orderByDesc('version')->get()->each->setAttribute('include_graph', false),
            ),
        );
    }

    public function show(Request $request, Workspace $workspace, Workflow $workflow, int $version): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(new WorkflowVersionResource($this->findVersion($workflow, $version)));
    }

    public function restore(Request $request, Workspace $workspace, Workflow $workflow, int $version): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $snapshot = $this->findVersion($workflow, $version);

        $workflow->replaceGraph($snapshot->graph['steps'] ?? [], $snapshot->graph['edges'] ?? []);

        return ApiResponse::success(
            new WorkflowResource($workflow->fresh()->load(['steps', 'edges.fromStep', 'edges.toStep', 'currentVersion'])),
            "Version {$version} restored into the draft. Publish to make it live.",
        );
    }

    public function diff(Request $request, Workspace $workspace, Workflow $workflow, int $from, int $to): JsonResponse
    {
        $this->ensureWorkflowBelongsToWorkspace($workspace, $workflow);

        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        $fromGraph = $this->findVersion($workflow, $from)->graph;
        $toGraph = $this->findVersion($workflow, $to)->graph;

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'steps' => $this->diffSteps($fromGraph['steps'] ?? [], $toGraph['steps'] ?? []),
            'edges' => $this->diffEdges($fromGraph['edges'] ?? [], $toGraph['edges'] ?? []),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $from
     * @param  array<int, array<string, mixed>>  $to
     * @return array{added: array<int, string>, removed: array<int, string>, changed: array<int, string>}
     */
    private function diffSteps(array $from, array $to): array
    {
        $fromByKey = collect($from)->keyBy('key');
        $toByKey = collect($to)->keyBy('key');

        $changed = $toByKey->filter(function (array $step, string $key) use ($fromByKey): bool {
            $old = $fromByKey->get($key);

            return $old !== null
                && ($old['type'] !== $step['type'] || ($old['config'] ?? []) != ($step['config'] ?? []));
        });

        return [
            'added' => $toByKey->keys()->diff($fromByKey->keys())->values()->all(),
            'removed' => $fromByKey->keys()->diff($toByKey->keys())->values()->all(),
            'changed' => $changed->keys()->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $from
     * @param  array<int, array<string, mixed>>  $to
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    private function diffEdges(array $from, array $to): array
    {
        $signature = fn (array $edge): string => $edge['from'].' -> '.$edge['to'].(isset($edge['condition']) && $edge['condition'] !== null ? " [{$edge['condition']}]" : '');

        $fromSet = collect($from)->map($signature);
        $toSet = collect($to)->map($signature);

        return [
            'added' => $toSet->diff($fromSet)->values()->all(),
            'removed' => $fromSet->diff($toSet)->values()->all(),
        ];
    }

    private function findVersion(Workflow $workflow, int $version): WorkflowVersion
    {
        return $workflow->versions()->where('version', $version)->firstOrFail();
    }

    private function ensureWorkflowBelongsToWorkspace(Workspace $workspace, Workflow $workflow): void
    {
        abort_if($workflow->workspace_id !== $workspace->id, 404);
    }
}
