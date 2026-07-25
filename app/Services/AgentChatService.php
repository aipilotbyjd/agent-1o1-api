<?php

namespace App\Services;

use App\Ai\WorkspaceAgent;
use App\Models\Agent;
use App\Models\Run;
use App\Models\User;
use App\Services\Workflows\TemplateResolver;
use Throwable;

class AgentChatService
{
    /**
     * Send a message to an agent, recording the exchange as a run.
     *
     * @return array{run: Run, reply: ?string, conversation_id: ?string, error: ?string}
     */
    public function send(Agent $agent, User $user, string $message, ?string $conversationId = null): array
    {
        $run = Run::create([
            'workspace_id' => $agent->workspace_id,
            'runnable_type' => $agent->getMorphClass(),
            'runnable_id' => $agent->id,
            'agent_version' => $agent->currentVersionNumber(),
            'trigger_type' => 'manual',
            'input' => ['message' => $message, 'conversation_id' => $conversationId],
            'triggered_by' => $user->id,
        ]);

        $step = $run->steps()->create([
            'key' => 'chat',
            'type' => 'agent',
            'input' => ['message' => $message],
        ]);

        $run->markRunning();
        $step->markRunning();

        try {
            $workspaceAgent = new WorkspaceAgent($agent, $run);

            $pending = $conversationId !== null
                ? $workspaceAgent->continue($conversationId, as: $user)
                : $workspaceAgent->forUser($user);

            $response = $pending->prompt(
                $message,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable $exception) {
            $step->markFailed($exception->getMessage());
            $run->markFailed($exception->getMessage());

            return ['run' => $run, 'reply' => null, 'conversation_id' => $conversationId, 'error' => $exception->getMessage()];
        }

        $output = ['reply' => $response->text, 'conversation_id' => $response->conversationId];
        $step->markCompleted($output, $response->usage->toArray());
        $run->markCompleted($output);

        return ['run' => $run, 'reply' => $response->text, 'conversation_id' => $response->conversationId, 'error' => null];
    }

    /**
     * Prompt an agent from a trigger: no user, no persisted conversation.
     * The trigger's message template is rendered against the incoming payload.
     *
     * @param  array<string, mixed>  $input
     */
    public function sendFromTrigger(Agent $agent, array $input, string $triggerType, ?string $messageTemplate = null): Run
    {
        $message = $messageTemplate !== null
            ? app(TemplateResolver::class)->resolve($messageTemplate, ['input' => $input])
            : ($input['message'] ?? json_encode($input));

        $run = Run::create([
            'workspace_id' => $agent->workspace_id,
            'runnable_type' => $agent->getMorphClass(),
            'runnable_id' => $agent->id,
            'agent_version' => $agent->currentVersionNumber(),
            'trigger_type' => $triggerType,
            'input' => ['message' => $message, 'payload' => $input],
        ]);

        $step = $run->steps()->create([
            'key' => 'chat',
            'type' => 'agent',
            'input' => ['message' => $message],
        ]);

        $run->markRunning();
        $step->markRunning();

        try {
            $response = (new WorkspaceAgent($agent, $run))->prompt(
                $message,
                provider: $agent->provider,
                model: $agent->model,
            );
        } catch (Throwable $exception) {
            $step->markFailed($exception->getMessage());
            $run->markFailed($exception->getMessage());

            return $run;
        }

        $output = ['reply' => $response->text];
        $step->markCompleted($output, $response->usage->toArray());
        $run->markCompleted($output);

        return $run;
    }
}
