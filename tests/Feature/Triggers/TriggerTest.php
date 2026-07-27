<?php

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Trigger;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

function triggerWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['done' => 'yes']]]);
    $workflow->publishVersion();

    return [$user, $workspace, $workflow];
}

it('creates a webhook trigger and returns its url', function () {
    [$user, $workspace, $workflow] = triggerWorkspace();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['type' => 'webhook'],
    );

    $response->assertCreated();
    expect($response->json('data.token'))->toHaveLength(40)
        ->and($response->json('data.webhook_url'))->toContain('/api/v1/hooks/');
});

it('creates a schedule trigger with a valid cron', function () {
    [$user, $workspace, $workflow] = triggerWorkspace();

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['type' => 'schedule', 'config' => ['cron' => '0 9 * * *']],
    )->assertCreated()->assertJsonPath('data.config.cron', '0 9 * * *');
});

it('rejects an invalid cron expression', function () {
    [$user, $workspace, $workflow] = triggerWorkspace();

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['type' => 'schedule', 'config' => ['cron' => 'not a cron']],
    )->assertUnprocessable()->assertJsonValidationErrors('config.cron');
});

it('forbids a regular member from creating triggers', function () {
    [$user, $workspace, $workflow] = triggerWorkspace('member');

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['type' => 'webhook'],
    )->assertForbidden();
});

it('starts a workflow run from a public webhook call', function () {
    [, , $workflow] = triggerWorkspace();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
    ]);

    $response = $this->postJson("/api/v1/hooks/{$trigger->token}", ['order' => 'ORD-9']);

    $response->assertStatus(202);

    $run = Run::find($response->json('data.run_id'));
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->trigger_type)->toBe('webhook')
        ->and($run->input)->toBe(['order' => 'ORD-9'])
        ->and($run->triggered_by)->toBeNull();
});

it('returns 404 for an unknown or inactive webhook token', function () {
    [, , $workflow] = triggerWorkspace();
    $inactive = Trigger::factory()->inactive()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
    ]);

    $this->postJson('/api/v1/hooks/definitely-not-a-token')->assertNotFound();
    $this->postJson("/api/v1/hooks/{$inactive->token}")->assertNotFound();
});

it('rejects a webhook for an unpublished workflow', function () {
    [, $workspace] = triggerWorkspace();
    $draft = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $draft->id,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}")->assertStatus(409);
});

it('dispatches due schedule triggers via the command', function () {
    [, , $workflow] = triggerWorkspace();
    Trigger::factory()->schedule('* * * * *')->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
    ]);

    $this->artisan('triggers:dispatch-due')->assertSuccessful();

    $run = Run::query()->latest('id')->first();
    expect($run)->not->toBeNull()
        ->and($run->trigger_type)->toBe('schedule')
        ->and($run->status)->toBe(RunStatus::Completed);
});

it('does not double dispatch a schedule trigger within the same minute', function () {
    [, , $workflow] = triggerWorkspace();
    Trigger::factory()->schedule('* * * * *')->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
    ]);

    $this->artisan('triggers:dispatch-due');
    $this->artisan('triggers:dispatch-due');

    expect(Run::query()->count())->toBe(1);
});

it('skips inactive schedule triggers', function () {
    [, , $workflow] = triggerWorkspace();
    Trigger::factory()->schedule('* * * * *')->inactive()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
    ]);

    $this->artisan('triggers:dispatch-due');

    expect(Run::query()->count())->toBe(0);
});
