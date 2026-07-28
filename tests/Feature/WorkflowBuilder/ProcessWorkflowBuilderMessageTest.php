<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Jobs\WorkflowBuilder\ProcessWorkflowBuilderMessage;
use App\Models\Workflows\WorkflowBuilderSession;

it('turns a pending message into a completed assistant reply', function () {
    WorkflowBuilderAgent::fake(["Sure, I've added a delay step."]);

    $session = WorkflowBuilderSession::factory()->create();
    $message = $session->messages()->create([
        'role' => 'user',
        'content' => 'Add a delay step.',
        'processing_status' => 'pending',
    ]);

    (new ProcessWorkflowBuilderMessage($message->id))->handle();

    $message->refresh();
    $session->refresh();

    expect($message->processing_status)->toBe('completed')
        ->and($session->messages()->where('role', 'assistant')->count())->toBe(1)
        ->and($session->messages()->where('role', 'assistant')->first()->content)->toBe("Sure, I've added a delay step.")
        ->and($session->conversation_id)->not->toBeNull();
});

it('ignores a message that is not pending', function () {
    $session = WorkflowBuilderSession::factory()->create();
    $message = $session->messages()->create([
        'role' => 'user',
        'content' => 'Already handled.',
        'processing_status' => 'completed',
    ]);

    (new ProcessWorkflowBuilderMessage($message->id))->handle();

    expect($session->messages()->count())->toBe(1);
});
