<?php

use App\Models\Triggers\Trigger;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\RateLimiter;

it('rate limits a single trigger token without affecting other triggers', function () {
    config(['triggers.hook_rate_limit_per_minute' => 2]);
    RateLimiter::clear('trigger-hooks');

    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();
    $hot = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    $other = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);

    $this->postJson("/api/v1/hooks/{$hot->token}");
    $this->postJson("/api/v1/hooks/{$hot->token}");
    $this->postJson("/api/v1/hooks/{$hot->token}")->assertStatus(429);

    $response = $this->postJson("/api/v1/hooks/{$other->token}");
    expect($response->status())->not->toBe(429);
});
