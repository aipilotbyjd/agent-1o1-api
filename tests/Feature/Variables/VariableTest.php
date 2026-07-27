<?php

use App\Models\User;
use App\Models\Variable;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

function variableWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists variables and redacts secret values', function () {
    [$user, $workspace] = variableWorkspace('member');
    Variable::factory()->create(['workspace_id' => $workspace->id, 'key' => 'greeting', 'value' => 'hello', 'is_secret' => false]);
    Variable::factory()->secret()->create(['workspace_id' => $workspace->id, 'key' => 'api_token', 'value' => 'super-secret']);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/variables");

    $response->assertOk()->assertJsonCount(2, 'data');

    $byKey = collect($response->json('data'))->keyBy('key');
    expect($byKey['greeting']['value'])->toBe('hello')
        ->and($byKey['api_token']['value'])->toBeNull()
        ->and($byKey['api_token']['is_secret'])->toBeTrue();
});

it('stores variable values encrypted regardless of is_secret', function () {
    [$user, $workspace] = variableWorkspace();

    $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/variables", [
        'key' => 'greeting',
        'value' => 'hello world',
        'is_secret' => false,
    ])->assertCreated();

    $stored = Variable::first();
    expect($stored->value)->toBe('hello world');

    $raw = DB::table('variables')->first();
    expect($raw->value)->not->toBe('hello world');
});

it('rejects a duplicate key within the same workspace', function () {
    [$user, $workspace] = variableWorkspace();
    Variable::factory()->create(['workspace_id' => $workspace->id, 'key' => 'greeting']);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/variables", [
        'key' => 'greeting',
        'value' => 'hi',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('key');
});

it('forbids a regular member from creating a variable', function () {
    [$user, $workspace] = variableWorkspace('member');

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/variables", [
        'key' => 'greeting',
        'value' => 'hi',
    ]);

    $response->assertForbidden();
});

it('allows an admin to update and delete a variable', function () {
    [$user, $workspace] = variableWorkspace();
    $variable = Variable::factory()->create(['workspace_id' => $workspace->id, 'value' => 'old']);

    $this->withToken(authHeader($user))->putJson("/api/v1/workspaces/{$workspace->id}/variables/{$variable->id}", [
        'value' => 'new',
    ])->assertOk()->assertJsonPath('data.value', 'new');

    $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/variables/{$variable->id}")
        ->assertOk();

    expect(Variable::find($variable->id))->toBeNull();
});
