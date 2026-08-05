<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\ProcessTriggerEvent;
use App\Models\Agents\Agent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Triggers\TriggerType;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerIntake;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Support\Facades\Queue;

function intakeWorkflow(): Workflow
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return $workflow;
}

function intakeTrigger(?Workflow $workflow = null, array $attributes = []): Trigger
{
    $workflow ??= intakeWorkflow();

    return Trigger::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        ...$attributes,
    ]);
}

it('stores the event and queues a job without starting a run inline', function () {
    Queue::fake();
    $trigger = intakeTrigger();

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['order' => 'ORD-1'])
        ->assertStatus(202)
        ->assertJsonPath('message', 'Event accepted.');

    $event = TriggerEvent::query()->where('trigger_id', $trigger->id)->sole();

    expect($event->status)->toBe(TriggerEventStatus::Pending)
        ->and($event->payload)->toBe(['order' => 'ORD-1'])
        ->and(Run::query()->count())->toBe(0);

    Queue::assertPushed(ProcessTriggerEvent::class, fn (ProcessTriggerEvent $job): bool => $job->eventId === $event->id);
});

it('persists the event even when the queue dispatch fails', function () {
    $trigger = intakeTrigger();

    // Simulates the queue being unreachable at the exact moment of dispatch. The
    // row must already be committed by then — that ordering is the whole reason
    // a lost job is recoverable rather than a lost event.
    $broken = Mockery::mock(Factory::class);
    $broken->shouldReceive('connection')->andThrow(new RuntimeException('queue down'));
    Queue::swap($broken);

    $threw = false;

    try {
        app(TriggerIntake::class)->accept($trigger, 'webhook', ['a' => 1]);
    } catch (RuntimeException) {
        $threw = true;
    }

    // Guards against this test passing for the wrong reason: if the dispatch no
    // longer goes through the queue factory, the failure was never simulated.
    expect($threw)->toBeTrue()
        ->and(TriggerEvent::query()->where('trigger_id', $trigger->id)->where('status', TriggerEventStatus::Pending)->exists())
        ->toBeTrue();
});

it('routes agent trigger events to their own queue', function () {
    Queue::fake();
    $workspace = Workspace::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_type' => $agent->getMorphClass(),
        'triggerable_id' => $agent->id,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['text' => 'hi'])->assertStatus(202);

    Queue::assertPushed(ProcessTriggerEvent::class, fn (ProcessTriggerEvent $job): bool => $job->queue === config('triggers.queues.agent'));
});

it('counts a retried delivery on the original event instead of storing a second row', function () {
    $type = TriggerType::factory()->githubPreset()->create(['mechanism' => 'webhook']);
    $trigger = intakeTrigger(attributes: ['trigger_type_id' => $type->id]);
    $headers = ['X-GitHub-Delivery' => 'delivery-1'];

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], $headers)->assertStatus(202);
    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], $headers)->assertOk();
    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], $headers)->assertOk();

    $event = TriggerEvent::query()->where('trigger_id', $trigger->id)->sole();

    expect($event->duplicate_count)->toBe(2)
        ->and(Run::query()->count())->toBe(1);
});

it('re-queues a previously failed delivery when the provider resends it', function () {
    $type = TriggerType::factory()->githubPreset()->create(['mechanism' => 'webhook']);
    $trigger = intakeTrigger(attributes: ['trigger_type_id' => $type->id]);

    TriggerEvent::factory()->failed()->create([
        'trigger_id' => $trigger->id,
        'delivery_id' => 'delivery-2',
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], ['X-GitHub-Delivery' => 'delivery-2'])
        ->assertStatus(202);

    $event = TriggerEvent::query()->where('trigger_id', $trigger->id)->sole();

    expect($event->status)->toBe(TriggerEventStatus::Matched)
        ->and(Run::query()->count())->toBe(1);
});

it('records a rejected signature without consuming the delivery id', function () {
    $type = TriggerType::factory()->githubPreset()->create([
        'mechanism' => 'webhook',
        'signature_scheme' => 'github',
    ]);
    $trigger = intakeTrigger(attributes: [
        'trigger_type_id' => $type->id,
        'signing_secret' => 'shhh',
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['ref' => 'main'], [
        'X-GitHub-Delivery' => 'delivery-3',
        'X-Hub-Signature-256' => 'sha256=wrong',
    ])->assertUnauthorized();

    $rejected = TriggerEvent::query()->where('trigger_id', $trigger->id)->sole();

    expect($rejected->status)->toBe(TriggerEventStatus::Rejected)
        ->and($rejected->delivery_id)->toBeNull();
});

it('resolves a draft target at intake rather than queueing it', function () {
    Queue::fake();
    $workspace = Workspace::factory()->create();
    $draft = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $trigger = Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $draft->id,
    ]);

    $this->postJson("/api/v1/hooks/{$trigger->token}", ['a' => 1])->assertStatus(409);

    expect(TriggerEvent::query()->where('trigger_id', $trigger->id)->sole()->status)
        ->toBe(TriggerEventStatus::Skipped);

    Queue::assertNothingPushed();
});
