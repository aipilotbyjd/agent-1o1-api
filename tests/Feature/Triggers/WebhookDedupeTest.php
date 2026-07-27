<?php

use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerType;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;

function dedupeWorkspace(): Workflow
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return $workflow;
}

it('ignores a github delivery retried with the same delivery id', function () {
    $workflow = dedupeWorkspace();
    $type = TriggerType::factory()->githubPreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $headers = ['X-GitHub-Delivery' => 'abc-123'];

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], $headers)->assertStatus(202);
    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], $headers)
        ->assertOk()->assertJsonPath('message', 'Duplicate delivery ignored.');

    expect(Run::query()->count())->toBe(1);
});

it('ignores a stripe event retried with the same payload id', function () {
    $workflow = dedupeWorkspace();
    $type = TriggerType::factory()->stripePreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $payload = ['id' => 'evt_dup', 'type' => 'charge.succeeded'];

    $this->postJson("/api/v1/hooks/{$trigger->token}", $payload)->assertStatus(202);
    $this->postJson("/api/v1/hooks/{$trigger->token}", $payload)
        ->assertOk()->assertJsonPath('message', 'Duplicate delivery ignored.');

    expect(Run::query()->count())->toBe(1);
});

it('ignores a slack retry within the retry window', function () {
    $workflow = dedupeWorkspace();
    $type = TriggerType::factory()->slackPreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", [])->assertStatus(202);
    $this->postJson("/api/v1/hooks/{$trigger->token}", [], ['X-Slack-Retry-Num' => '1'])
        ->assertOk()->assertJsonPath('message', 'Duplicate delivery ignored.');

    expect(Run::query()->count())->toBe(1);
});
