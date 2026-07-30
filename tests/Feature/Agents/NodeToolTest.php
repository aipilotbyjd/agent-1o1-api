<?php

use App\Ai\Tools\NodeTool;
use App\Enums\Runs\RunStepStatus;
use App\Models\Agents\Agent;
use App\Models\Credentials\Credential;
use App\Models\Nodes\Node;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\Nodes\NodeCatalogSync;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

/**
 * Attach a node to an agent and hand back the tool the agent would expose for it.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $attachment
 */
function nodeToolFor(array $attributes = [], array $attachment = []): NodeTool
{
    $workspace = Workspace::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);

    $node = Node::factory()->custom()
        ->callingUrl('https://api.example.com/orders')
        ->create(['workspace_id' => $workspace->id, ...$attributes]);

    $agent->nodes()->attach($node->id, [
        'config' => $attachment['config'] ?? null,
        'exposed_fields' => $attachment['exposed_fields'] ?? null,
    ]);

    return new NodeTool($agent->nodes()->first(), $agent);
}

/**
 * @return array<string, Type>
 */
function nodeToolSchema(NodeTool $tool): array
{
    return $tool->schema(new JsonSchemaTypeFactory);
}

it('executes an attached node and logs a run step', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

    $tool = nodeToolFor(['type' => 'order_lookup']);
    $tool->run = Run::factory()->running()->create(['workspace_id' => $tool->agent->workspace_id]);

    $result = (string) $tool->handle(new ToolRequest(['query' => 'ORD-1']));

    // The model receives the structured result encoded as JSON text.
    expect(json_decode($result, true))
        ->toMatchArray(['ok' => true, 'status' => 200, 'json' => ['ok' => true]]);

    $step = $tool->run->steps()->first();
    expect($step->key)->toBe('node:order_lookup')
        ->and($step->type)->toBe('tool')
        ->and($step->status)->toBe(RunStepStatus::Completed)
        ->and($step->output['status'])->toBe(200);
});

it('logs a failed run step when the call throws', function () {
    Http::fake(fn () => throw new Exception('Connection refused'));

    $tool = nodeToolFor();
    $tool->run = Run::factory()->running()->create(['workspace_id' => $tool->agent->workspace_id]);

    expect((string) $tool->handle(new ToolRequest(['query' => 'x'])))->toContain('Tool execution failed')
        ->and($tool->run->steps()->first()->status)->toBe(RunStepStatus::Failed);
});

it('opens a run of its own when the agent was not given one', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

    $tool = nodeToolFor();
    $tool->handle(new ToolRequest([]));

    $run = Run::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->runnable_id)->toBe($tool->agent->id)
        ->and($run->trigger_type)->toBe('agent')
        ->and($run->steps()->count())->toBe(1);
});

it('flattens the dotted catalog type into a tool name', function () {
    $node = Node::factory()->make(['type' => 'slack.post_message']);

    expect((new NodeTool($node, Agent::factory()->make()))->name())->toBe('slack_post_message');
});

it('sends non-GET arguments as a json body', function () {
    Http::fake(['api.example.com/*' => Http::response('created', 201)]);

    nodeToolFor(['config' => ['url' => 'https://api.example.com/orders', 'method' => 'POST']])
        ->handle(new ToolRequest(['sku' => 'A1']));

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['sku'] === 'A1');
});

describe('what the model is allowed to fill', function () {
    beforeEach(function () {
        $this->schema = [
            'type' => 'object',
            'properties' => [
                'channel' => ['type' => 'string'],
                'text' => ['type' => 'string', 'description' => 'What to post.'],
                'silent' => ['type' => 'boolean'],
            ],
            'required' => ['channel', 'text'],
        ];
    });

    it('offers every config field when nothing is bound', function () {
        $types = nodeToolSchema(nodeToolFor(['config_schema' => $this->schema]));

        expect(array_keys($types))->toBe(['channel', 'text', 'silent'])
            ->and($types['text']->toArray())->toMatchArray(['description' => 'What to post.']);
    });

    it('hides a field whose value was bound at attach time', function () {
        $types = nodeToolSchema(nodeToolFor(
            ['config_schema' => $this->schema],
            ['config' => ['channel' => '#alerts']],
        ));

        expect(array_keys($types))->toBe(['text', 'silent']);
    });

    it('narrows the offered fields to the exposed list', function () {
        $types = nodeToolSchema(nodeToolFor(
            ['config_schema' => $this->schema],
            ['exposed_fields' => ['text']],
        ));

        expect(array_keys($types))->toBe(['text']);
    });

    it('never offers the credential, even when the node declares one', function () {
        $types = nodeToolSchema(nodeToolFor(['config_schema' => [
            'type' => 'object',
            'properties' => ['credential_id' => ['type' => 'integer'], 'query' => ['type' => 'string']],
        ]]));

        expect(array_keys($types))->toBe(['query']);
    });

    it('drops a field with no json schema equivalent rather than breaking the agent', function () {
        $types = nodeToolSchema(nodeToolFor(['config_schema' => [
            'type' => 'object',
            'properties' => ['broken' => ['type' => 'nonsense'], 'query' => ['type' => 'string']],
        ]]));

        expect(array_keys($types))->toBe(['query']);
    });
});

it('lets bound config win over an argument the model tried to set anyway', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

    nodeToolFor(
        ['config_schema' => ['type' => 'object', 'properties' => ['channel' => ['type' => 'string']]]],
        ['config' => ['channel' => '#alerts']],
    )->handle(new ToolRequest(['channel' => '#anything-else']));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'channel=%23alerts'));
});

it('applies the node credential without the model ever seeing it', function () {
    Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

    $workspace = Workspace::factory()->create();
    $credential = Credential::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'abc123'],
    ]);

    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $node = Node::factory()->custom()
        ->callingUrl('https://api.example.com/orders')
        ->create(['workspace_id' => $workspace->id, 'credential_id' => $credential->id]);
    $agent->nodes()->attach($node->id);

    (new NodeTool($agent->nodes()->first(), $agent))->handle(new ToolRequest(['query' => 'x']));

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer abc123'));
    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('exposes a builtin catalog connector to an agent, not just a custom node', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

    app(NodeCatalogSync::class)->sync();

    $workspace = Workspace::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $httpNode = Node::query()->where('type', 'http.request')->sole();

    $agent->nodes()->attach($httpNode->id, [
        'config' => ['url' => 'https://api.example.com/pinned', 'method' => 'GET'],
        'exposed_fields' => ['query'],
    ]);

    $tool = new NodeTool($agent->nodes()->first(), $agent);

    expect($tool->name())->toBe('http_request')
        ->and(array_keys(nodeToolSchema($tool)))->toBe(['query']);

    $tool->handle(new ToolRequest(['query' => ['q' => 'hello']]));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.example.com/pinned'));
});
