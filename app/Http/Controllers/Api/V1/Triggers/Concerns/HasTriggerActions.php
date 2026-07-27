<?php

namespace App\Http\Controllers\Api\V1\Triggers\Concerns;

use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\V1\Triggers\UpdateTriggerRequest;
use App\Http\Resources\V1\TriggerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Trigger;
use App\Models\Workspace;
use App\Services\TriggerBuilder;
use App\Services\TriggerFiringService;
use App\Services\Triggers\TriggerEventRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

trait HasTriggerActions
{
    protected function ensureTriggerBelongsToParent(Trigger $trigger, Model $parent): void
    {
        abort_if($trigger->triggerable_id !== $parent->getKey() || $trigger->triggerable_type !== $parent->getMorphClass(), 404);
    }

    protected function indexTriggers(Request $request, Workspace $workspace, Model $parent): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success(
            TriggerResource::collection(
                Trigger::query()
                    ->where('triggerable_type', $parent->getMorphClass())
                    ->where('triggerable_id', $parent->getKey())
                    ->latest()
                    ->get(),
            ),
        );
    }

    protected function storeTrigger(StoreTriggerRequest $request, Workspace $workspace, Model $parent, TriggerBuilder $builder): JsonResponse
    {
        $trigger = $builder->create($workspace, $parent, $request->user(), $request->validated());

        return ApiResponse::created(new TriggerResource($trigger->load('triggerType')), 'Trigger created.');
    }

    protected function updateTrigger(UpdateTriggerRequest $request, Trigger $trigger): JsonResponse
    {
        $trigger->update($request->validated());

        return ApiResponse::success(new TriggerResource($trigger->fresh('triggerType')), 'Trigger updated.');
    }

    protected function destroyTrigger(Request $request, Workspace $workspace, Trigger $trigger): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        $trigger->delete();

        return ApiResponse::success(null, 'Trigger deleted.');
    }

    protected function runTrigger(
        Request $request,
        Workspace $workspace,
        Trigger $trigger,
        TriggerFiringService $firing,
        TriggerEventRecorder $events,
    ): JsonResponse {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin', 'member')) {
            return ApiResponse::forbidden();
        }

        if ($firing->hasInFlightRun($trigger)) {
            return ApiResponse::error('A run is already in progress for this trigger target.', Response::HTTP_CONFLICT);
        }

        try {
            $run = $firing->fire($trigger, ['triggered_by' => 'manual', 'user_id' => $request->user()->id], 'manual');
        } catch (Throwable $e) {
            $events->record($trigger, 'manual', false, error: $e->getMessage());
            report($e);

            return ApiResponse::error('This trigger failed to start a run.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($run === null) {
            $events->record($trigger, 'manual', false, error: 'Target not runnable');

            return ApiResponse::error('This trigger is not currently runnable.', Response::HTTP_CONFLICT);
        }

        $events->record($trigger, 'manual', true, run: $run);

        return ApiResponse::success(['run_id' => $run->id], 'Run started.', Response::HTTP_ACCEPTED);
    }

    protected function rotateTriggerToken(Request $request, Workspace $workspace, Trigger $trigger): JsonResponse
    {
        if (! $request->user()->hasWorkspaceRole($workspace, 'owner', 'admin')) {
            return ApiResponse::forbidden();
        }

        if ($trigger->type !== 'webhook') {
            return ApiResponse::error('Only webhook triggers have a token to rotate.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $trigger->update(['token' => Str::random(40)]);

        return ApiResponse::success(new TriggerResource($trigger), 'Trigger token rotated.');
    }
}
