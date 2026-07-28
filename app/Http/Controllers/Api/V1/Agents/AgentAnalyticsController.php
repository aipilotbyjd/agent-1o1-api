<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Runs\RunStatus;
use App\Enums\Runs\RunStepStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Runs\RunStep;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Aggregate usage analytics for a single agent, derived from its run history:
 * volume, success rate, latency, token usage, and per-step-type failure rates.
 */
class AgentAnalyticsController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentAnalyticsView);

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $runs = $agent->runs()
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'trigger_type', 'created_at', 'started_at', 'finished_at']);

        $completed = $runs->where('status', RunStatus::Completed)->count();
        $failed = $runs->where('status', RunStatus::Failed)->count();
        $finished = $completed + $failed;

        $durations = $runs
            ->filter(fn ($run): bool => $run->started_at !== null && $run->finished_at !== null)
            ->map(fn ($run): float => $run->started_at->diffInMilliseconds($run->finished_at));

        $steps = RunStep::query()
            ->whereIn('run_id', $runs->pluck('id'))
            ->get(['run_id', 'type', 'status', 'usage']);

        $promptTokens = (int) $steps->sum(fn (RunStep $step): int => (int) ($step->usage['prompt_tokens'] ?? 0));
        $completionTokens = (int) $steps->sum(fn (RunStep $step): int => (int) ($step->usage['completion_tokens'] ?? 0));
        $totalTokens = $promptTokens + $completionTokens;

        $byDay = $runs
            ->groupBy(fn ($run): string => $run->created_at->toDateString())
            ->map(fn ($group, $day): array => [
                'day' => $day,
                'runs' => $group->count(),
                'failed' => $group->where('status', RunStatus::Failed)->count(),
            ])
            ->sortKeys()
            ->values();

        $stepStats = $steps
            ->groupBy(fn (RunStep $step): string => $step->type)
            ->map(function ($group, $type): array {
                $calls = $group->count();
                $failures = $group->where('status', RunStepStatus::Failed)->count();

                return [
                    'type' => $type,
                    'calls' => $calls,
                    'failures' => $failures,
                    'failure_rate' => $calls > 0 ? round($failures / $calls, 4) : 0.0,
                ];
            })
            ->sortByDesc('calls')
            ->values();

        return ApiResponse::success([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'total_runs' => $runs->count(),
                'completed' => $completed,
                'failed' => $failed,
                'running' => $runs->where('status', RunStatus::Running)->count(),
                'success_rate' => $finished > 0 ? round($completed / $finished, 4) : null,
            ],
            'tokens' => [
                'total' => $totalTokens,
                'prompt' => $promptTokens,
                'completion' => $completionTokens,
                'avg_per_run' => $runs->isNotEmpty() ? (int) round($totalTokens / $runs->count()) : 0,
            ],
            'latency' => [
                'avg_duration_ms' => (int) round((float) ($durations->avg() ?? 0)),
                'max_duration_ms' => (int) round((float) ($durations->max() ?? 0)),
            ],
            'by_trigger_type' => $runs->countBy('trigger_type'),
            'by_day' => $byDay,
            'steps' => $stepStats,
        ]);
    }
}
