<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\Runs\RunStepStatus;
use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Runs\RunFixSuggestionResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Runs\DiagnoseFailedRunStep;
use App\Models\Runs\Run;
use App\Models\Runs\RunFixSuggestion;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunFixSuggestionController extends Controller
{
    public function index(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);

        $this->requirePermission(Permission::RunView);

        return ApiResponse::success(
            RunFixSuggestionResource::collection($run->fixSuggestions()->latest()->get()),
        );
    }

    public function diagnose(Request $request, Workspace $workspace, Run $run): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);

        $this->requirePermission(Permission::WorkflowManage);

        $validated = $request->validate([
            'step_key' => ['required', 'string'],
        ]);

        $step = $run->steps()->where('key', $validated['step_key'])->first();

        if ($step === null) {
            return ApiResponse::notFound('No such step on this run.');
        }

        if ($step->status !== RunStepStatus::Failed) {
            return ApiResponse::error('Only failed steps can be diagnosed.', 422);
        }

        DiagnoseFailedRunStep::dispatch($run->id, $step->key, $request->user()->id);

        return ApiResponse::success(null, 'Diagnosis queued. Suggestions will appear shortly.', 202);
    }

    public function apply(Request $request, Workspace $workspace, Run $run, RunFixSuggestion $fixSuggestion): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);
        abort_if($fixSuggestion->run_id !== $run->id, 404);

        $this->requirePermission(Permission::WorkflowManage);

        $index = (int) $request->validate([
            'suggestion_index' => ['required', 'integer', 'min:0'],
        ])['suggestion_index'];

        $fixConfig = $fixSuggestion->suggestions[$index]['fix_config'] ?? null;

        if (! is_array($fixConfig) || $fixConfig === []) {
            return ApiResponse::error('Selected suggestion has no applicable fix.', 422);
        }

        if (! $run->runnable instanceof Workflow) {
            return ApiResponse::error('Fixes can only be applied to workflow runs.', 422);
        }

        $step = $run->runnable->steps()->where('key', $fixSuggestion->step_key)->first();

        if ($step === null) {
            return ApiResponse::error('The workflow no longer has this step.', 422);
        }

        $step->update(['config' => array_merge($step->config ?? [], $fixConfig)]);
        $run->runnable->update(['has_unpublished_changes' => true]);
        $fixSuggestion->update(['status' => RunFixSuggestion::STATUS_APPLIED]);

        return ApiResponse::success(
            new RunFixSuggestionResource($fixSuggestion),
            'Fix applied to the workflow draft.',
        );
    }

    public function dismiss(Request $request, Workspace $workspace, Run $run, RunFixSuggestion $fixSuggestion): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $run);
        abort_if($fixSuggestion->run_id !== $run->id, 404);

        $this->requirePermission(Permission::WorkflowManage);

        $fixSuggestion->update(['status' => RunFixSuggestion::STATUS_DISMISSED]);

        return ApiResponse::success(null, 'Suggestion dismissed.');
    }
}
