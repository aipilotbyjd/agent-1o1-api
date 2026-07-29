<?php

use App\Exceptions\Http\BlockedOutboundUrlException;
use App\Models\Credentials\Credential;
use App\Models\Runs\ConnectorMetric;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Nodes\Connectors\CodeExpressionNode;
use App\Services\Workflows\Nodes\Connectors\EmailSendNode;
use App\Services\Workflows\Nodes\Connectors\HttpRequestNode;
use App\Services\Workflows\Nodes\Connectors\SlackPostMessageNode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function connectorRun(): Run
{
    $workspace = Workspace::factory()->create();

    return Run::factory()->running()->create(['workspace_id' => $workspace->id]);
}

describe('http.request', function () {
    it('returns a structured response and records a connector metric', function () {
        Http::fake(['api.example.com/*' => Http::response(['items' => [1, 2]], 200)]);
        $run = connectorRun();

        $output = app(HttpRequestNode::class)->execute($run, [
            'url' => 'https://api.example.com/things',
            'method' => 'GET',
        ], []);

        expect($output['ok'])->toBeTrue()
            ->and($output['status'])->toBe(200)
            ->and($output['json']['items'])->toBe([1, 2])
            ->and(ConnectorMetric::query()->where('connector', 'http.request')->exists())->toBeTrue();
    });

    it('sends a json body for a non-GET request', function () {
        Http::fake(['api.example.com/*' => Http::response('', 201)]);
        $run = connectorRun();

        app(HttpRequestNode::class)->execute($run, [
            'url' => 'https://api.example.com/things',
            'method' => 'POST',
            'body' => ['name' => 'Ada', 'count' => 3],
        ], []);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->data() === ['name' => 'Ada', 'count' => 3]);
    });

    it('applies a bearer credential from the same workspace', function () {
        Http::fake(['api.example.com/*' => Http::response('', 200)]);
        $run = connectorRun();
        $credential = Credential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'type' => Credential::TYPE_BEARER_TOKEN,
            'data' => ['token' => 'tok-123456'],
        ]);

        app(HttpRequestNode::class)->execute($run, [
            'url' => 'https://api.example.com/things',
            'credential_id' => $credential->id,
        ], []);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok-123456'));
    });

    it('blocks a request to a private address', function () {
        $run = connectorRun();

        expect(fn () => app(HttpRequestNode::class)->execute($run, ['url' => 'http://169.254.169.254/latest/meta-data'], []))
            ->toThrow(BlockedOutboundUrlException::class);
    });

    it('reports a non-2xx response as ok false rather than throwing', function () {
        Http::fake(['api.example.com/*' => Http::response(['error' => 'bad'], 500)]);
        $run = connectorRun();

        $output = app(HttpRequestNode::class)->execute($run, ['url' => 'https://api.example.com/x'], []);

        expect($output['ok'])->toBeFalse()->and($output['status'])->toBe(500);
    });
});

describe('slack.post_message', function () {
    it('posts a message and returns the thread timestamp', function () {
        Http::fake(['slack.com/*' => Http::response(['ok' => true, 'channel' => 'C1', 'ts' => '1700000000.1'], 200)]);
        $run = connectorRun();
        $credential = Credential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'type' => Credential::TYPE_BEARER_TOKEN,
            'data' => ['token' => 'xoxb-secret-token'],
        ]);

        $output = app(SlackPostMessageNode::class)->execute($run, [
            'credential_id' => $credential->id,
            'channel' => '#general',
            'text' => 'Deploy finished.',
        ], []);

        expect($output['ok'])->toBeTrue()
            ->and($output['ts'])->toBe('1700000000.1');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer xoxb-secret-token')
            && $request->data()['channel'] === '#general');
    });

    it('treats a 200 with ok false as a failure', function () {
        Http::fake(['slack.com/*' => Http::response(['ok' => false, 'error' => 'channel_not_found'], 200)]);
        $run = connectorRun();
        $credential = Credential::factory()->create([
            'workspace_id' => $run->workspace_id,
            'type' => Credential::TYPE_BEARER_TOKEN,
            'data' => ['token' => 'xoxb-secret-token'],
        ]);

        $output = app(SlackPostMessageNode::class)->execute($run, [
            'credential_id' => $credential->id,
            'channel' => '#nope',
            'text' => 'hi',
        ], []);

        expect($output['ok'])->toBeFalse()
            ->and($output['error'])->toBe('channel_not_found');
    });

    it('fails when the credential belongs to another workspace', function () {
        $run = connectorRun();
        $foreign = Credential::factory()->create(['type' => Credential::TYPE_BEARER_TOKEN]);

        expect(fn () => app(SlackPostMessageNode::class)->execute($run, [
            'credential_id' => $foreign->id,
            'channel' => '#general',
            'text' => 'hi',
        ], []))->toThrow(InvalidArgumentException::class);
    });
});

describe('email.send', function () {
    it('sends a plain-text email', function () {
        Mail::fake();
        $run = connectorRun();

        $output = app(EmailSendNode::class)->execute($run, [
            'to' => 'ada@example.com',
            'subject' => 'Run finished',
            'body' => 'All good.',
        ], []);

        expect($output)->toBe(['sent' => true, 'to' => 'ada@example.com']);
    });

    it('rejects an invalid address', function () {
        Mail::fake();
        $run = connectorRun();

        expect(fn () => app(EmailSendNode::class)->execute($run, [
            'to' => 'not-an-email',
            'subject' => 'x',
            'body' => 'y',
        ], []))->toThrow(InvalidArgumentException::class);
    });
});

describe('code.expression', function () {
    it('computes named values from the run context', function () {
        $run = connectorRun();

        $output = app(CodeExpressionNode::class)->execute($run, [
            'expressions' => [
                'total' => 'input.price * input.quantity',
                'label' => "upper(input.name) + ' x' + number(input.quantity)",
                'is_bulk' => 'input.quantity >= 10',
            ],
        ], ['input' => ['price' => 2.5, 'quantity' => 10, 'name' => 'widget']]);

        expect($output['total'])->toBe(25.0)
            ->and($output['label'])->toBe('WIDGET x10')
            ->and($output['is_bulk'])->toBeTrue();
    });
});
