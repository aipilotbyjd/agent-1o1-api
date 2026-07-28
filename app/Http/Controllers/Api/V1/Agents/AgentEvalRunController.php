<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Agents\AgentEvalRunResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;
use App\Services\Agents\AgentEvalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentEvalRunController extends Controller
{
    public function __construct(private readonly AgentEvalService $evals) {}

    public function index(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        $this->ensureSuiteBelongsToAgent($workspace, $agent, $evalSuite);

        $this->requirePermission(Permission::AgentEvalView);

        return ApiResponse::success(AgentEvalRunResource::collection($evalSuite->runs()->get()));
    }

    public function store(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        $this->ensureSuiteBelongsToAgent($workspace, $agent, $evalSuite);

        $this->requirePermission(Permission::AgentEvalRun);

        if ($evalSuite->cases()->count() === 0) {
            return ApiResponse::error('This suite has no cases to run.', 422);
        }

        $evalRun = $this->evals->run($evalSuite, $request->user());

        return ApiResponse::success(new AgentEvalRunResource($evalRun), 'Eval run completed.');
    }

    private function ensureSuiteBelongsToAgent(Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): void
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($evalSuite->agent_id !== $agent->id, 404);
    }
}
