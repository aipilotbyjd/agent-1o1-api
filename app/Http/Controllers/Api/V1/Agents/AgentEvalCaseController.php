<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentEvalCaseRequest;
use App\Http\Resources\V1\Agents\AgentEvalCaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentEvalCaseController extends Controller
{
    public function store(StoreAgentEvalCaseRequest $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        $this->ensureSuiteBelongsToAgent($workspace, $agent, $evalSuite);

        $case = $evalSuite->cases()->create([
            ...$request->validated(),
            'sort_order' => (int) ($evalSuite->cases()->max('sort_order') ?? -1) + 1,
        ]);

        return ApiResponse::created(new AgentEvalCaseResource($case), 'Eval case added.');
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite, AgentEvalCase $case): JsonResponse
    {
        $this->ensureSuiteBelongsToAgent($workspace, $agent, $evalSuite);
        abort_if($case->suite_id !== $evalSuite->id, 404);

        $this->requirePermission(Permission::AgentEvalManage);

        $case->delete();

        return ApiResponse::success(null, 'Eval case deleted.');
    }

    private function ensureSuiteBelongsToAgent(Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): void
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($evalSuite->agent_id !== $agent->id, 404);
    }
}
