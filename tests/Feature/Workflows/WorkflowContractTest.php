<?php

use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function contractWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('generates a node signature from the current graph when creating a snapshot', function () {
    [$user, $workspace] = contractWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $workflow->replaceGraph(
        [
            ['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 5], 'position' => null],
            ['key' => 's2', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']], 'position' => null],
        ],
        [['from' => 's1', 'to' => 's2', 'condition' => null]],
    );

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots",
        [],
    );

    $response->assertCreated();
    $signature = $response->json('data.node_signature');

    expect($signature['steps'])->toHaveCount(2)
        ->and(collect($signature['steps'])->pluck('key')->all())->toBe(['s1', 's2'])
        ->and(collect($signature['steps'])->firstWhere('key', 's1')['type'])->toBe('delay')
        ->and($signature['edges'])->toHaveCount(1);
});

it('passes a contract test run when the workflow has not changed', function () {
    [$user, $workspace] = contractWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $workflow->replaceGraph(
        [['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 5], 'position' => null]],
        [],
    );

    $snapshot = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots",
        [],
    )->json('data');

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots/{$snapshot['id']}/test-runs",
        [],
    );

    $response->assertCreated()
        ->assertJsonPath('data.status', 'passed')
        ->assertJsonPath('data.results.drifted', false);
});

it('fails a contract test run and reports drift when steps change', function () {
    [$user, $workspace] = contractWorkspace();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $workflow->replaceGraph(
        [
            ['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 5], 'position' => null],
            ['key' => 's2', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']], 'position' => null],
        ],
        [],
    );

    $snapshot = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots",
        [],
    )->json('data');

    // Remove s2, add s3, and change s1's config keys.
    $workflow->replaceGraph(
        [
            ['key' => 's1', 'type' => 'delay', 'config' => ['seconds' => 10, 'label' => 'wait'], 'position' => null],
            ['key' => 's3', 'type' => 'transform', 'config' => ['mapping' => ['new' => '1']], 'position' => null],
        ],
        [],
    );

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots/{$snapshot['id']}/test-runs",
        [],
    );

    $response->assertCreated()->assertJsonPath('data.status', 'failed');

    $results = $response->json('data.results');
    expect($results['drifted'])->toBeTrue()
        ->and($results['added_steps'])->toBe(['s3'])
        ->and($results['removed_steps'])->toBe(['s2'])
        ->and(collect($results['changed_steps'])->pluck('key')->all())->toBe(['s1']);
});

it('forbids a regular member from creating a contract snapshot', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract-snapshots",
        [],
    )->assertForbidden();
});
