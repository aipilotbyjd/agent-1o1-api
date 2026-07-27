<?php

use App\Models\Run;
use App\Models\Trigger;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\TriggerTypeSeeder;

function catalogSetup(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return [$admin, $workspace, $workflow];
}

it('lists the trigger type catalog grouped by category', function () {
    $this->seed(TriggerTypeSeeder::class);
    [$admin] = catalogSetup();

    $response = $this->withToken(authHeader($admin))->getJson('/api/v1/trigger-types');

    $response->assertOk();
    expect($response->json('data.schedule'))->toHaveCount(5)
        ->and($response->json('data.github'))->toHaveCount(4)
        ->and(collect($response->json('data'))->flatten(1)->count())->toBe(19);
});

it('creates a trigger from a catalog type with preset cron', function () {
    $this->seed(TriggerTypeSeeder::class);
    [$admin, $workspace, $workflow] = catalogSetup();

    $response = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['trigger_type_key' => 'schedule.daily'],
    );

    $response->assertCreated()
        ->assertJsonPath('data.type', 'schedule')
        ->assertJsonPath('data.config.cron', '0 9 * * *')
        ->assertJsonPath('data.trigger_type.key', 'schedule.daily');
});

it('lets user config override the preset', function () {
    $this->seed(TriggerTypeSeeder::class);
    [$admin, $workspace, $workflow] = catalogSetup();

    $response = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['trigger_type_key' => 'schedule.daily', 'config' => ['cron' => '30 17 * * *']],
    );

    $response->assertCreated()->assertJsonPath('data.config.cron', '30 17 * * *');
});

it('requires a cron for the custom cron catalog type', function () {
    $this->seed(TriggerTypeSeeder::class);
    [$admin, $workspace, $workflow] = catalogSetup();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['trigger_type_key' => 'schedule.cron'],
    )->assertUnprocessable()->assertJsonValidationErrors('config.cron');
});

it('creates a github push trigger whose filters gate the webhook', function () {
    $this->seed(TriggerTypeSeeder::class);
    [$admin, $workspace, $workflow] = catalogSetup();

    $secret = 'whsec_test_secret';

    $created = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers",
        ['trigger_type_key' => 'github.push', 'signing_secret' => $secret],
    );

    $created->assertCreated()->assertJsonPath('data.type', 'webhook');
    $token = $created->json('data.token');

    $pushPayload = json_encode(['ref' => 'refs/heads/main']);
    $pingPayload = json_encode(['zen' => 'hi']);

    // Matching event fires a run.
    $this->postJson("/api/v1/hooks/{$token}", ['ref' => 'refs/heads/main'], [
        'X-GitHub-Event' => 'push',
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $pushPayload, $secret),
    ])->assertStatus(202);

    // Non-matching event is acknowledged but ignored.
    $this->postJson("/api/v1/hooks/{$token}", ['zen' => 'hi'], [
        'X-GitHub-Event' => 'ping',
        'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $pingPayload, $secret),
    ])->assertOk()->assertJsonPath('message', 'Event ignored by trigger filters.');

    expect(Run::query()->count())->toBe(1);
});

it('filters on payload paths', function () {
    [, $workspace, $workflow] = catalogSetup();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'config' => ['filters' => [['source' => 'payload', 'path' => 'event.type', 'operator' => 'equals', 'value' => 'app_mention']]],
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['event' => ['type' => 'message']])
        ->assertOk()->assertJsonPath('message', 'Event ignored by trigger filters.');

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['event' => ['type' => 'app_mention']])
        ->assertStatus(202);

    expect(Run::query()->count())->toBe(1);
});
