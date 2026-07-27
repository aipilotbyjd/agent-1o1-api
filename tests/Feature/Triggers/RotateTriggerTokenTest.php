<?php

use App\Models\Trigger;
use App\Models\TriggerEvent;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

it('rotates a webhook trigger token without losing event history', function () {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    TriggerEvent::factory()->create(['trigger_id' => $trigger->id]);
    $oldToken = $trigger->token;

    $response = $this->withToken(authHeader($admin))->postJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}/rotate-token",
    );

    $response->assertOk();
    $newToken = $response->json('data.token');

    expect($newToken)->not->toBe($oldToken)
        ->and($trigger->triggerEvents()->count())->toBe(1);

    $this->postJson("/api/v1/hooks/{$oldToken}")->assertNotFound();
    $this->postJson("/api/v1/hooks/{$newToken}")->assertStatus(202);
});
