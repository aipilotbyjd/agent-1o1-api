<?php

use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function updateTriggerSetup(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);

    return [$admin, $workspace, $workflow];
}

it('updates is_active and config without rotating the token', function () {
    [$admin, $workspace, $workflow] = updateTriggerSetup();
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);
    $originalToken = $trigger->token;

    $response = $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}",
        ['is_active' => false],
    );

    $response->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.token', $originalToken);
});

it('forbids a member from updating a trigger', function () {
    [, $workspace, $workflow] = updateTriggerSetup();
    $member = User::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $workflow->id]);

    $this->withToken(authHeader($member))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}",
        ['is_active' => false],
    )->assertForbidden();
});

it('returns 404 when the trigger does not belong to the workflow', function () {
    [$admin, $workspace, $workflow] = updateTriggerSetup();
    $otherWorkflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $trigger = Trigger::factory()->create(['workspace_id' => $workspace->id, 'triggerable_id' => $otherWorkflow->id]);

    $this->withToken(authHeader($admin))->putJson(
        "/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/triggers/{$trigger->id}",
        ['is_active' => false],
    )->assertNotFound();
});
