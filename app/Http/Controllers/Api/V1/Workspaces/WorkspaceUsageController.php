<?php

namespace App\Http\Controllers\Api\V1\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RunStep;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceUsageController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $steps = RunStep::query()
            ->whereNotNull('usage')
            ->whereHas('run', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->when($validated['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->with('run:id,runnable_type,runnable_id')
            ->get();

        $totals = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'steps' => 0];
        $byRunnable = [];
        $byDay = [];

        foreach ($steps as $step) {
            $prompt = (int) ($step->usage['prompt_tokens'] ?? 0);
            $completion = (int) ($step->usage['completion_tokens'] ?? 0);

            $totals['prompt_tokens'] += $prompt;
            $totals['completion_tokens'] += $completion;
            $totals['total_tokens'] += $prompt + $completion;
            $totals['steps']++;

            $runnableKey = class_basename((string) $step->run->runnable_type).':'.$step->run->runnable_id;
            $byRunnable[$runnableKey] = ($byRunnable[$runnableKey] ?? 0) + $prompt + $completion;

            $day = $step->created_at->toDateString();
            $byDay[$day] = ($byDay[$day] ?? 0) + $prompt + $completion;
        }

        ksort($byDay);

        return ApiResponse::success([
            'totals' => $totals,
            'by_runnable' => $byRunnable,
            'by_day' => $byDay,
        ]);
    }
}
