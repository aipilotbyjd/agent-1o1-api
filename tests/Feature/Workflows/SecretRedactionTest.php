<?php

use App\Models\Credentials\Credential;
use App\Models\Runs\RunLog;
use App\Models\Tool;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;
use App\Services\Runs\SecretRedactor;
use Illuminate\Support\Facades\Http;

function redactionWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    return [$admin, $workspace];
}

it('masks a secret variable value found in a run log message', function () {
    [$admin, $workspace] = redactionWorkspace();
    Variable::factory()->secret()->create([
        'workspace_id' => $workspace->id,
        'key' => 'api_token',
        'value' => 'super-secret-token-value',
    ]);

    $redacted = app(SecretRedactor::class)->redact(
        $workspace->id,
        'Request failed with token super-secret-token-value in the query string.',
    );

    expect($redacted)->toBe('Request failed with token [redacted] in the query string.')
        ->and($redacted)->not->toContain('super-secret-token-value');
});

it('masks credential values anywhere in a nested structure', function () {
    [$admin, $workspace] = redactionWorkspace();
    Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'bearer-abcdef-123456'],
    ]);

    $redacted = app(SecretRedactor::class)->redact($workspace->id, [
        'headers' => ['Authorization' => 'Bearer bearer-abcdef-123456'],
        'status' => 401,
    ]);

    expect($redacted)->toBe([
        'headers' => ['Authorization' => 'Bearer [redacted]'],
        'status' => 401,
    ]);
});

it('leaves short values alone so ordinary text is not mangled', function () {
    [$admin, $workspace] = redactionWorkspace();
    Variable::factory()->secret()->create([
        'workspace_id' => $workspace->id,
        'key' => 'flag',
        'value' => 'on',
    ]);

    expect(app(SecretRedactor::class)->redact($workspace->id, 'turning on the lights'))
        ->toBe('turning on the lights');
});

it('does not leak a credential value into a failed step run log', function () {
    Http::fake(['api.example.com/*' => Http::response('denied for token tok-live-9876543210', 403)]);

    [$admin, $workspace] = redactionWorkspace();

    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'tok-live-9876543210'],
    ]);

    $tool = Tool::factory()->create([
        'workspace_id' => $workspace->id,
        'credential_id' => $credential->id,
        'config' => ['url' => 'https://api.example.com/thing', 'method' => 'GET', 'parameters' => []],
    ]);

    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create([
        'key' => 'call',
        'type' => 'transform',
        'config' => ['mapping' => ['note' => 'token is {{ variables.missing }}']],
    ]);
    $workflow->publishVersion();

    $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/trigger",
    )->assertCreated();

    $logged = RunLog::query()->where('workspace_id', $workspace->id)->pluck('message')->implode(' ');

    expect($logged)->not->toContain('tok-live-9876543210');
});
