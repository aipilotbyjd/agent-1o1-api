<?php

use App\Models\Credentials\Credential;
use App\Models\Credentials\CredentialType;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function credentialWorkspace(string $role = 'admin'): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->{$role}()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('lists credentials without exposing their data', function () {
    [$user, $workspace] = credentialWorkspace('member');
    Credential::factory()->create(['workspace_id' => $workspace->id, 'data' => ['api_key' => 'super-secret']]);

    $response = $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/credentials");

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0'))->not->toHaveKey('data');
});

it('allows an admin to create a credential', function () {
    [$user, $workspace] = credentialWorkspace();
    CredentialType::factory()->create(['key' => 'api_key', 'is_active' => true]);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/credentials", [
        'name' => 'Stripe Live Key',
        'type' => 'api_key',
        'data' => ['header' => 'Authorization', 'value' => 'sk_live_xxx'],
    ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Stripe Live Key');
    expect($response->json('data'))->not->toHaveKey('data');

    $stored = Credential::first();
    expect($stored->data)->toBe(['header' => 'Authorization', 'value' => 'sk_live_xxx']);

    $raw = DB::table('credentials')->first();
    expect($raw->data)->not->toContain('sk_live_xxx');
});

it('rejects creating a credential with an unknown type', function () {
    [$user, $workspace] = credentialWorkspace();

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/credentials", [
        'name' => 'Bad',
        'type' => 'not_a_real_type',
        'data' => ['value' => 'x'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('forbids a regular member from creating a credential', function () {
    [$user, $workspace] = credentialWorkspace('member');
    CredentialType::factory()->create(['key' => 'api_key', 'is_active' => true]);

    $response = $this->withToken(authHeader($user))->postJson("/api/v1/workspaces/{$workspace->id}/credentials", [
        'name' => 'x',
        'type' => 'api_key',
        'data' => ['value' => 'x'],
    ]);

    $response->assertForbidden();
});

it('allows an admin to update and delete a credential', function () {
    [$user, $workspace] = credentialWorkspace();
    $credential = Credential::factory()->create(['workspace_id' => $workspace->id]);

    $this->withToken(authHeader($user))->putJson("/api/v1/workspaces/{$workspace->id}/credentials/{$credential->id}", [
        'name' => 'Renamed',
    ])->assertOk()->assertJsonPath('data.name', 'Renamed');

    $this->withToken(authHeader($user))->deleteJson("/api/v1/workspaces/{$workspace->id}/credentials/{$credential->id}")
        ->assertOk();

    expect(Credential::find($credential->id))->toBeNull();
});

it('returns 404 for a credential belonging to another workspace', function () {
    [$user, $workspace] = credentialWorkspace();
    $credential = Credential::factory()->create();

    $this->withToken(authHeader($user))->getJson("/api/v1/workspaces/{$workspace->id}/credentials/{$credential->id}")
        ->assertNotFound();
});
