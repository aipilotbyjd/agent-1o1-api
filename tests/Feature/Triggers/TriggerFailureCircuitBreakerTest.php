<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\ProcessTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerFiringService;
use App\Services\Workflows\WorkflowRunner;

function circuitBreakerWorkflow(): Workflow
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return $workflow;
}

/**
 * Failures are counted when an event is finally given up on, not per attempt, so
 * these drive the job's failed() hook directly — that is the only thing that
 * advances the streak now.
 */
it('deactivates a trigger after the configured number of permanently failed events', function () {
    config(['triggers.max_consecutive_failures' => 3]);

    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create(['workspace_id' => $workflow->workspace_id, 'triggerable_id' => $workflow->id]);

    for ($i = 0; $i < 3; $i++) {
        // processing(), not pending(): an event that was actually attempted. Only
        // those count — see ProcessTriggerEvent::failed().
        $event = TriggerEvent::factory()->processing()->create(['trigger_id' => $trigger->id]);

        (new ProcessTriggerEvent($event->id, $trigger->id))->failed(new RuntimeException('boom'));

        expect($event->fresh()->status)->toBe(TriggerEventStatus::Failed);
    }

    $trigger->refresh();
    expect($trigger->consecutive_failure_count)->toBe(3)
        ->and($trigger->is_active)->toBeFalse();
});

it('does not count retries of a single event against the streak', function () {
    config(['triggers.max_consecutive_failures' => 3]);

    $this->mock(WorkflowRunner::class, function ($mock): void {
        $mock->shouldReceive('start')->andThrow(new RuntimeException('transient'));
    });

    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create(['workspace_id' => $workflow->workspace_id, 'triggerable_id' => $workflow->id]);
    $event = TriggerEvent::factory()->pending()->create(['trigger_id' => $trigger->id]);

    // Three attempts at the same event. Only the terminal failed() call counts.
    for ($i = 0; $i < 3; $i++) {
        try {
            (new ProcessTriggerEvent($event->id, $trigger->id))->handle(app(TriggerFiringService::class));
        } catch (RuntimeException) {
            // Expected — the retry would be scheduled by the queue.
        }
    }

    expect($trigger->fresh()->consecutive_failure_count)->toBe(0)
        ->and($trigger->fresh()->is_active)->toBeTrue();
});

it('does not count an event that expired without ever being attempted', function () {
    config(['triggers.max_consecutive_failures' => 3]);

    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create(['workspace_id' => $workflow->workspace_id, 'triggerable_id' => $workflow->id]);

    // Never claimed — it aged out of the retry window sitting behind a backlog.
    // That is a queue problem, not evidence the target is broken.
    $event = TriggerEvent::factory()->pending()->create(['trigger_id' => $trigger->id]);

    (new ProcessTriggerEvent($event->id, $trigger->id))->failed(new RuntimeException('expired'));

    expect($event->fresh()->status)->toBe(TriggerEventStatus::Failed)
        ->and($trigger->fresh()->consecutive_failure_count)->toBe(0)
        ->and($trigger->fresh()->is_active)->toBeTrue();
});

it('does not overwrite an event that already succeeded', function () {
    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create(['workspace_id' => $workflow->workspace_id, 'triggerable_id' => $workflow->id]);
    $event = TriggerEvent::factory()->create(['trigger_id' => $trigger->id]);

    (new ProcessTriggerEvent($event->id, $trigger->id))->failed(new RuntimeException('late failure'));

    expect($event->fresh()->status)->toBe(TriggerEventStatus::Matched)
        ->and($trigger->fresh()->consecutive_failure_count)->toBe(0);
});

it('resets the failure counter after a successful fire', function () {
    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'consecutive_failure_count' => 2,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['a' => 1])->assertStatus(202);

    expect($trigger->fresh()->consecutive_failure_count)->toBe(0);
});
