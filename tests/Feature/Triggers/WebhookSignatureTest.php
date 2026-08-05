<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerType;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Models\Workspaces\WorkspaceMember;

function signatureWorkspace(): array
{
    $admin = User::factory()->create();
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return [$admin, $workspace, $workflow];
}

it('accepts a github webhook with a valid signature', function () {
    [, $workspace, $workflow] = signatureWorkspace();
    $type = TriggerType::factory()->githubPreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->withSigningSecret('whsec_abc')->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $payload = json_encode(['ref' => 'refs/heads/main']);
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'whsec_abc');

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'refs/heads/main'], [
        'X-Hub-Signature-256' => $signature,
    ])->assertStatus(202);

    expect(Run::query()->count())->toBe(1);
});

it('rejects a github webhook with an invalid signature', function () {
    [, $workspace, $workflow] = signatureWorkspace();
    $type = TriggerType::factory()->githubPreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->withSigningSecret('whsec_abc')->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $response = $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'refs/heads/main'], [
        'X-Hub-Signature-256' => 'sha256=deadbeef',
    ]);

    $response->assertStatus(401);
    expect(Run::query()->count())->toBe(0)
        ->and($trigger->triggerEvents()->where('status', TriggerEventStatus::Rejected)->where('error', 'Invalid signature')->exists())->toBeTrue();
});

it('rejects a stripe webhook with an expired timestamp', function () {
    [, $workspace, $workflow] = signatureWorkspace();
    $type = TriggerType::factory()->stripePreset()->create(['mechanism' => 'webhook']);
    $trigger = Trigger::factory()->withSigningSecret('whsec_stripe')->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
    ]);

    $payload = json_encode(['id' => 'evt_1', 'type' => 'charge.succeeded']);
    $timestamp = now()->subMinutes(10)->timestamp;
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_stripe');

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['id' => 'evt_1', 'type' => 'charge.succeeded'], [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertStatus(401);
});

it('passes through when no signature scheme is configured', function () {
    [, $workspace, $workflow] = signatureWorkspace();
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['a' => 1])->assertStatus(202);
});
