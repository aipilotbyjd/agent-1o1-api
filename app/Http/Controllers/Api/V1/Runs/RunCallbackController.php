<?php

namespace App\Http\Controllers\Api\V1\Runs;

use App\Enums\Runs\RunStepStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Runs\RunStep;
use App\Services\Workflows\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Resumes a workflow parked on a wait step.
 *
 * Public by design — the external system being waited on has no workspace session, so
 * the single-use token in the URL is the whole credential. It is cleared the moment the
 * step resumes, which is what makes a replayed callback a 404 rather than a second
 * traversal of the graph.
 */
class RunCallbackController extends Controller
{
    public function __construct(public WorkflowRunner $runner) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $step = RunStep::query()
            ->where('callback_token', $token)
            ->where('status', RunStepStatus::AwaitingCallback->value)
            ->first();

        if ($step === null) {
            return ApiResponse::notFound();
        }

        if ($step->callback_expires_at !== null && $step->callback_expires_at->isPast()) {
            return ApiResponse::error('This callback has expired.');
        }

        // Two deliveries racing would otherwise both read the step as still waiting and
        // advance the graph twice.
        $lock = Cache::lock("run-step:{$step->id}:callback", 10);

        return $lock->block(3, function () use ($step, $request): JsonResponse {
            $step->refresh();

            if ($step->status !== RunStepStatus::AwaitingCallback) {
                return ApiResponse::error('This step is no longer waiting for a callback.');
            }

            $run = $step->run;

            if ($run === null) {
                return ApiResponse::notFound();
            }

            $this->runner->resolveCallback($run, $step, $request->all());

            return ApiResponse::success(['run_id' => $run->id], 'Run resumed.');
        });
    }
}
