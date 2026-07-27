<?php

use App\Models\Runs\Run;
use App\Models\Runs\RunReplayPack;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

it('replays a pack against its captured graph without touching the live published version', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $workflow->replaceGraph([['key' => 's1', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']], 'position' => null]], []);
    $workflow->publishVersion();
    $snapshot = $workflow->currentVersion->graph;

    $pack = RunReplayPack::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'version_snapshot' => $snapshot,
        'trigger_data' => ['foo' => 'bar'],
    ]);

    // The live workflow moves on to a different published version.
    $workflow->replaceGraph([['key' => 'different', 'type' => 'delay', 'config' => ['seconds' => 1], 'position' => null]], []);
    $liveVersion = $workflow->publishVersion();

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/replay-packs/{$pack->id}/replay",
    );

    $response->assertCreated()->assertJsonPath('data.trigger_type', 'replay');

    $run = Run::find($response->json('data.id'));

    expect($run->workflowVersion->graph)->toBe($snapshot)
        ->and($run->workflow_version_id)->not->toBe($liveVersion->id)
        ->and($workflow->fresh()->current_version_id)->toBe($liveVersion->id);
});

it('forbids a regular member from replaying a pack', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $pack = RunReplayPack::factory()->create(['workspace_id' => $workspace->id, 'workflow_id' => $workflow->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/replay-packs/{$pack->id}/replay",
    )->assertForbidden();
});
