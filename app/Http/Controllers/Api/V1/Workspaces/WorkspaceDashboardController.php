<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Enums\Runs\RunStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workspace overview: entity counts plus a 30-day run activity summary,
 * recent failures, and the most-run workflows.
 */
class WorkspaceDashboardController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $this->requirePermission(Permission::WorkspaceView);

        $since = now()->subDays(30)->startOfDay();

        $recentRuns = $workspace->runs()
            ->where('created_at', '>=', $since)
            ->get(['id', 'runnable_type', 'runnable_id', 'status', 'created_at']);

        $recentFailures = $workspace->runs()
            ->with('runnable:id,name')
            ->where('status', RunStatus::Failed)
            ->latest()
            ->limit(10)
            ->get(['id', 'runnable_type', 'runnable_id', 'error', 'finished_at', 'created_at']);

        $topWorkflows = $recentRuns
            ->where('runnable_type', 'workflow')
            ->countBy('runnable_id')
            ->sortDesc()
            ->take(5);

        $workflowNames = $workspace->workflows()
            ->whereIn('id', $topWorkflows->keys())
            ->pluck('name', 'id');

        return ApiResponse::success([
            'counts' => [
                'workflows' => $workspace->workflows()->count(),
                'agents' => $workspace->agents()->count(),
                'nodes' => $workspace->nodes()->count(),
                'members' => $workspace->members()->count(),
            ],
            'runs_last_30_days' => [
                'total' => $recentRuns->count(),
                'completed' => $recentRuns->where('status', RunStatus::Completed)->count(),
                'failed' => $recentRuns->where('status', RunStatus::Failed)->count(),
                'running' => $recentRuns->where('status', RunStatus::Running)->count(),
                'by_day' => $recentRuns
                    ->groupBy(fn ($run): string => $run->created_at->toDateString())
                    ->map(fn ($group, $day): array => ['day' => $day, 'runs' => $group->count()])
                    ->sortKeys()
                    ->values(),
            ],
            'top_workflows' => $topWorkflows
                ->map(fn (int $count, int $workflowId): array => [
                    'workflow_id' => $workflowId,
                    'name' => $workflowNames[$workflowId] ?? null,
                    'runs' => $count,
                ])
                ->values(),
            'recent_failures' => $recentFailures->map(fn ($run): array => [
                'run_id' => $run->id,
                'runnable_type' => $run->runnable_type,
                'runnable_name' => $run->runnable?->name,
                'error' => $run->error,
                'failed_at' => $run->finished_at ?? $run->created_at,
            ]),
        ]);
    }
}
