<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Api\V1\Triggers\Concerns\HasTriggerActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Triggers\StoreTriggerRequest;
use App\Http\Requests\Api\V1\Triggers\UpdateTriggerRequest;
use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerBuilder;
use App\Services\Triggers\TriggerFiringService;
use App\Services\Triggers\TriggerIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTriggerController extends Controller
{
    use HasTriggerActions;

    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return $this->indexTriggers($request, $workspace, $workflow);
    }

    public function store(StoreTriggerRequest $request, Workspace $workspace, Workflow $workflow, TriggerBuilder $builder): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);

        return $this->storeTrigger($request, $workspace, $workflow, $builder);
    }

    public function update(UpdateTriggerRequest $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $this->ensureTriggerBelongsToParent($trigger, $workflow);

        return $this->updateTrigger($request, $trigger);
    }

    public function destroy(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $this->ensureTriggerBelongsToParent($trigger, $workflow);

        return $this->destroyTrigger($request, $workspace, $trigger);
    }

    public function run(
        Request $request,
        Workspace $workspace,
        Workflow $workflow,
        Trigger $trigger,
        TriggerFiringService $firing,
        TriggerIntake $intake,
    ): JsonResponse {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $this->ensureTriggerBelongsToParent($trigger, $workflow);

        return $this->runTrigger($request, $workspace, $trigger, $firing, $intake);
    }

    public function rotateToken(Request $request, Workspace $workspace, Workflow $workflow, Trigger $trigger): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $workflow);
        $this->ensureTriggerBelongsToParent($trigger, $workflow);

        return $this->rotateTriggerToken($request, $workspace, $trigger);
    }
}
