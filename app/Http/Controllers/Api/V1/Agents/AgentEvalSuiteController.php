<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Enums\Workspaces\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\StoreAgentEvalSuiteRequest;
use App\Http\Resources\V1\Agents\AgentEvalSuiteResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Workspaces\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentEvalSuiteController extends Controller
{
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $this->requirePermission(Permission::AgentEvalView);

        $suites = $agent->evalSuites()
            ->withCount('cases')
            ->latest()
            ->get();

        return ApiResponse::success(AgentEvalSuiteResource::collection($suites));
    }

    public function store(StoreAgentEvalSuiteRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);

        $suite = $agent->evalSuites()->create([
            'workspace_id' => $agent->workspace_id,
            'created_by' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        foreach ($request->validated('cases') ?? [] as $index => $case) {
            $suite->cases()->create([
                'name' => $case['name'],
                'input' => $case['input'],
                'assertions' => $case['assertions'],
                'sort_order' => $index,
            ]);
        }

        return ApiResponse::created(new AgentEvalSuiteResource($suite->load('cases')), 'Eval suite created.');
    }

    public function show(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($evalSuite->agent_id !== $agent->id, 404);

        $this->requirePermission(Permission::AgentEvalView);

        return ApiResponse::success(new AgentEvalSuiteResource($evalSuite->load(['cases', 'runs'])));
    }

    public function destroy(Request $request, Workspace $workspace, Agent $agent, AgentEvalSuite $evalSuite): JsonResponse
    {
        $this->ensureBelongsToWorkspace($workspace, $agent);
        abort_if($evalSuite->agent_id !== $agent->id, 404);

        $this->requirePermission(Permission::AgentEvalManage);

        $evalSuite->delete();

        return ApiResponse::success(null, 'Eval suite deleted.');
    }
}
