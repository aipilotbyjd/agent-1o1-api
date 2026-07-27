<?php

namespace App\Jobs;

use App\Ai\WorkflowBuilderAgent;
use App\Models\WorkflowBuilderMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWorkflowBuilderMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = WorkflowBuilderMessage::with('session')->find($this->messageId);

        if ($message === null || $message->processing_status !== 'pending') {
            return;
        }

        $session = $message->session;
        $message->update(['processing_status' => 'processing']);

        $agent = new WorkflowBuilderAgent($session);

        if ($session->conversation_id !== null) {
            $agent->continue($session->conversation_id, $session->user);
        } else {
            $agent->forUser($session->user);
        }

        try {
            $response = $agent->prompt($message->content);
        } catch (Throwable $exception) {
            $message->update(['processing_status' => 'failed', 'error_message' => $exception->getMessage()]);

            return;
        }

        if ($session->conversation_id === null && $agent->currentConversation() !== null) {
            $session->update(['conversation_id' => $agent->currentConversation()]);
        }

        $draftVersion = $session->draftVersions()->latest()->first();

        $session->messages()->create([
            'draft_version_id' => $draftVersion?->id,
            'role' => 'assistant',
            'content' => $response->text,
            'processing_status' => 'completed',
        ]);

        $message->update(['processing_status' => 'completed']);
        $session->update(['last_activity_at' => now()]);
    }
}
