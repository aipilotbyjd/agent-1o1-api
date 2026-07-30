<?php

namespace App\Services\Agents;

use App\Ai\Agents\WorkspaceAgent;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Workflows\TemplateResolver;
use Closure;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

class AgentChatService
{
    public function __construct(private readonly TemplateResolver $templates) {}

    /**
     * Send a message to an agent, recording the exchange as a run.
     *
     * @return array{run: Run, reply: ?string, conversation_id: ?string, error: ?string}
     */
    public function send(Agent $agent, User $user, string $message, ?string $conversationId = null): array
    {
        $run = $this->execute(
            $agent,
            $message,
            'manual',
            fn (WorkspaceAgent $workspaceAgent): AgentResponse => $workspaceAgent->askAs($message, $user, $conversationId),
            input: ['message' => $message, 'conversation_id' => $conversationId],
            user: $user,
        );

        $output = $run->output ?? [];

        return [
            'run' => $run,
            'reply' => $output['reply'] ?? null,
            'conversation_id' => $output['conversation_id'] ?? $conversationId,
            'error' => $run->error,
        ];
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
            ? $this->templates->resolve($messageTemplate, ['input' => $input])
            : ($input['message'] ?? json_encode($input));

        return $this->execute(
            $agent,
            $message,
            $triggerType,
            fn (WorkspaceAgent $workspaceAgent): AgentResponse => $workspaceAgent->ask($message),
            input: ['message' => $message, 'payload' => $input],
        );
    }

    /**
     * Record one agent turn as a run: create it, prompt, and settle both the run and its
     * single `chat` step on the way out.
     *
     * @param  Closure(WorkspaceAgent): AgentResponse  $prompt
     * @param  array<string, mixed>  $input
     */
    private function execute(
        Agent $agent,
        string $message,
        string $triggerType,
        Closure $prompt,
        array $input,
        ?User $user = null,
    ): Run {
        $run = Run::create([
            'workspace_id' => $agent->workspace_id,
            'runnable_type' => $agent->getMorphClass(),
            'runnable_id' => $agent->id,
            'agent_version' => $agent->currentVersionNumber(),
            'trigger_type' => $triggerType,
            'input' => $input,
            'triggered_by' => $user?->id,
        ]);

        $step = $run->steps()->create([
            'key' => 'chat',
            'type' => 'agent',
            'input' => ['message' => $message],
        ]);

        $run->markRunning();
        $step->markRunning();

        try {
            $response = $prompt(new WorkspaceAgent($agent, $run));
        } catch (Throwable $exception) {
            $step->markFailed($exception->getMessage());
            $run->markFailed($exception->getMessage());

            return $run;
        }

        // The conversation id is only meaningful for the conversational path; leaving it
        // off entirely would make a trigger run's output a different shape every time.
        $output = array_filter(
            ['reply' => $response->text, 'conversation_id' => $response->conversationId ?? null],
            fn (mixed $value): bool => $value !== null,
        );

        $step->markCompleted($output, $response->usage->toArray());
        $run->markCompleted($output);

        return $run;
    }
}
