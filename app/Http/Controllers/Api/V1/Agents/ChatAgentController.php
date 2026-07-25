<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agents\ChatAgentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\AgentChatService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ChatAgentController extends Controller
{
    public function __construct(public AgentChatService $chat) {}

    public function __invoke(ChatAgentRequest $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        abort_if($agent->workspace_id !== $workspace->id, 404);

        $result = $this->chat->send(
            $agent,
            $request->user(),
            $request->validated('message'),
            $request->validated('conversation_id'),
        );

        if ($result['error'] !== null) {
            return ApiResponse::error('The agent failed to respond.', Response::HTTP_BAD_GATEWAY, [
                'run_id' => $result['run']->id,
            ]);
        }

        return ApiResponse::success([
            'reply' => $result['reply'],
            'conversation_id' => $result['conversation_id'],
            'run_id' => $result['run']->id,
        ]);
    }
}
