<?php

use App\Models\Credentials\Credential;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use Illuminate\Support\Facades\Http;

function toolCredentialWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

    return [$user, $workspace];
}

it('applies a bearer token credential to the outbound request', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    [$user, $workspace] = toolCredentialWorkspace();

    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'abc123'],
    ]);
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}/test",
        ['arguments' => ['query' => 'x']],
    )->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer abc123'));
    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('applies a basic auth credential to the outbound request', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    [$user, $workspace] = toolCredentialWorkspace();

    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BASIC_AUTH,
        'data' => ['username' => 'alice', 'password' => 'secret'],
    ]);
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}/test",
        ['arguments' => ['query' => 'x']],
    )->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader(
        'Authorization',
        'Basic '.base64_encode('alice:secret'),
    ));
});

it('applies an api key credential as a named header', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    [$user, $workspace] = toolCredentialWorkspace();

    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_API_KEY,
        'data' => ['header' => 'X-Api-Key', 'value' => 'super-secret'],
    ]);
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id]);

    $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}/test",
        ['arguments' => ['query' => 'x']],
    )->assertOk();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Api-Key', 'super-secret'));
});

it('fails when the tool credential has expired', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);
    [$user, $workspace] = toolCredentialWorkspace();

    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'abc123'],
        'expires_at' => now()->subDay(),
    ]);
    $tool = Tool::factory()->create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id]);

    $response = $this->withToken(authHeader($user))->postJson(
        "/api/v1/workspaces/{$workspace->id}/tools/{$tool->id}/test",
        ['arguments' => ['query' => 'x']],
    );

    $response->assertStatus(400);
    Http::assertNothingSent();
});
