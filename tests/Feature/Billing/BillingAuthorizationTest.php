<?php

use App\Models\Billing\UsagePeriod;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

it('allows workspace admins to view credits', function () {
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    UsagePeriod::factory()->create(['workspace_id' => $workspace->id, 'credits_limit' => 1000, 'credits_used' => 250]);

    $response = $this->withToken(authHeader($admin))->getJson("/api/v1/workspaces/{$workspace->id}/billing/credits");

    $response->assertOk()->assertJsonPath('data.credits_remaining', 750);
});

it('forbids regular members from viewing credits', function () {
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $this->withToken(authHeader($member))->getJson("/api/v1/workspaces/{$workspace->id}/billing/credits")
        ->assertForbidden();
});

it('forbids regular members from checking out a subscription', function () {
    $member = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->member()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    $this->withToken(authHeader($member))->postJson("/api/v1/workspaces/{$workspace->id}/billing/subscription/checkout", [
        'plan_id' => 1,
        'interval' => 'monthly',
    ])->assertForbidden();
});
