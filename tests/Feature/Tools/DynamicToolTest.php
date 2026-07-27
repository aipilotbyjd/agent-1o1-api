<?php

use App\Ai\Tools\DynamicTool;
use App\Enums\Runs\RunStepStatus;
use App\Models\Runs\Run;
use App\Models\Tool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

it('executes an http tool and logs a run step', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);
    $tool = Tool::factory()->create(['slug' => 'order_lookup']);
    $run = Run::factory()->running()->create(['workspace_id' => $tool->workspace_id]);

    $result = (string) (new DynamicTool($tool, $run))->handle(new ToolRequest(['query' => 'ORD-1']));

    expect($result)->toContain('HTTP 200');

    $step = $run->steps()->first();
    expect($step->key)->toBe('tool:order_lookup')
        ->and($step->type)->toBe('tool')
        ->and($step->status)->toBe(RunStepStatus::Completed)
        ->and($step->input)->toBe(['query' => 'ORD-1']);
});

it('logs a failed run step when the handler throws', function () {
    Http::fake(fn () => throw new Exception('Connection refused'));
    $tool = Tool::factory()->create();
    $run = Run::factory()->running()->create(['workspace_id' => $tool->workspace_id]);

    $result = (string) (new DynamicTool($tool, $run))->handle(new ToolRequest(['query' => 'x']));

    expect($result)->toContain('Tool execution failed');
    expect($run->steps()->first()->status)->toBe(RunStepStatus::Failed);
});

it('sends non-GET arguments as a json body', function () {
    Http::fake(['api.example.com/*' => Http::response('created', 201)]);
    $tool = Tool::factory()->create([
        'config' => ['url' => 'https://api.example.com/orders', 'method' => 'POST'],
    ]);

    (new DynamicTool($tool))->handle(new ToolRequest(['sku' => 'A1']));

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['sku'] === 'A1');
});

it('exposes the tool slug as the tool name with underscores', function () {
    $tool = Tool::factory()->make(['slug' => 'order-lookup']);

    expect((new DynamicTool($tool))->name())->toBe('order_lookup');
});
