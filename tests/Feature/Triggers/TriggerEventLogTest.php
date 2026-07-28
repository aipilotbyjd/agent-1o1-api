<?php

use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function eventLogSetup(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return [$admin, $workspace, $workflow];
}

it('records a matched trigger event when a webhook fires a run', function () {
    [, $workspace, $workflow] = eventLogSetup();
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['a' => 1])->assertStatus(202);

    expect(TriggerEvent::query()->where('trigger_id', $trigger->id)->where('matched', true)->exists())->toBeTrue();
});

it('records an unmatched trigger event when filters reject the payload', function () {
    [, $workspace, $workflow] = eventLogSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'config' => ['filters' => [['source' => 'payload', 'path' => 'x', 'operator' => 'equals', 'value' => 'y']]],
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['x' => 'nope'])->assertOk();

    expect(TriggerEvent::query()->where('trigger_id', $trigger->id)->where('matched', false)->exists())->toBeTrue();
});

it('lists paginated trigger events for a workflow trigger', function () {
    [$admin, $workspace, $workflow] = eventLogSetup();
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    TriggerEvent::factory()->count(3)->create(['trigger_id' => $trigger->id]);

    $response = $this->withToken(authHeader($admin))->getJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/events",
    );

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('forbids a non-member from reading trigger events', function () {
    [, $workspace, $workflow] = eventLogSetup();
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    $outsider = User::factory()->create();

    $this->withToken(authHeader($outsider))->getJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/events",
    )->assertForbidden();
});
