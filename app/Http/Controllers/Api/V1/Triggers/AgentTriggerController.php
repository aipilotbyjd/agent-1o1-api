<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Api\V1\Triggers\Concerns\HasTriggerActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\V1\Triggers\UpdateTriggerRequest;
use App\Models\Agent;
use App\Models\Trigger;
use App\Models\Workspace;
use App\Services\TriggerBuilder;
use App\Services\TriggerFiringService;
use App\Services\Triggers\TriggerEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTriggerController extends Controller
{
    use HasTriggerActions;

    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        return $this->indexTriggers($request, $workspace, $agent);
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Agent $agent, TriggerBuilder $builder): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);

        return $this->storeTrigger($request, $workspace, $agent, $builder);
    }

    public function update(UpdateTriggerRequest $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->updateTrigger($request, $trigger);
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
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
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->runTrigger($request, $workspace, $trigger, $firing, $events);
    }

    public function rotateToken(Request $request, Workspace $workspace, Agent $agent, Trigger $trigger): JsonResponse
    {
        $this->ensureAgentBelongsToWorkspace($workspace, $agent);
        $this->ensureTriggerBelongsToParent($trigger, $agent);

        return $this->rotateTriggerToken($request, $workspace, $trigger);
    }

    private function ensureAgentBelongsToWorkspace(Workspace $workspace, Agent $agent): void
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);
    }
}
