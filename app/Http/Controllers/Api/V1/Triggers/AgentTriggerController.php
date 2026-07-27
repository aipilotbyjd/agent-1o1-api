<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Api\V1\Triggers\Concerns\HasTriggerActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\V1\Triggers\UpdateTriggerRequest;
use App\Models\Agents\Agent;
use App\Models\Triggers\Trigger;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerBuilder;
use App\Services\Triggers\TriggerEventRecorder;
use App\Services\Triggers\TriggerFiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTriggerController extends Controller
{
    use HasTriggerActions;

    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return $this->indexTriggers($request, $workspace, $agent);
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Agent $agent, TriggerBuilder $builder): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        return $this->storeTrigger($request, $workspace, $agent, $builder);
    }

    public function update(UpdateTriggerRequest $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->updateTrigger($request, $trigger);
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->destroyTrigger($request, $workspace, $trigger);
    }

    public function run(
        Request $request,
        Workspace $workspace,
        Agent $agent,
        Trigger $trigger,
        TriggerFiringService $firing,
        TriggerEventRecorder $events,
    ): JsonResponse {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->runTrigger($request, $workspace, $trigger, $firing, $events);
    }

    public function rotateToken(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->rotateTriggerToken($request, $workspace, $trigger);
    }
}
