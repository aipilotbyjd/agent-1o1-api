<?php

use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\WorkflowRunner;

function circuitBreakerWorkflow(): Workflow
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return $workflow;
}

it('deactivates a trigger after the configured number of consecutive failures', function () {
    config(['triggers.max_consecutive_failures' => 3]);
    $this->mock(WorkflowRunner::class, function ($mock): void {
        $mock->shouldReceive('start')->andThrow(new RuntimeException('boom'));
    });

    $workflow = circuitBreakerWorkflow();
    $trigger = Trigger::factory()->create(['workspace_id' => $workflow->workspace_id, 'triggerable_id' => $workflow->id]);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson("/api/v1/hooks/{$trigger->token}", ['a' => 1])->assertStatus(500);
    }

    $trigger->refresh();
    expect($trigger->consecutive_failure_count)->toBe(3)
        ->and($trigger->is_active)->toBeFalse();
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
