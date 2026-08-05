<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Jobs\Triggers\ProcessTriggerEvent;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerFiringService;
use Illuminate\Support\Facades\Queue;

function reconcileTrigger(array $attributes = []): Trigger
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return Trigger::factory()->create([
        'workspace_id' => $workspace->id,
        'triggerable_id' => $workflow->id,
        ...$attributes,
    ]);
}

it('re-queues an event whose job never ran', function () {
    Queue::fake();
    $trigger = reconcileTrigger();
    $stranded = TriggerEvent::factory()->pending()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('triggers:reconcile')->assertSuccessful();

    Queue::assertPushed(ProcessTriggerEvent::class, fn (ProcessTriggerEvent $job): bool => $job->eventId === $stranded->id);
});

it('re-queues an event abandoned mid-processing by a dead worker', function () {
    Queue::fake();
    $trigger = reconcileTrigger();
    $abandoned = TriggerEvent::factory()->processing()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $this->artisan('triggers:reconcile')->assertSuccessful();

    // Reset to pending so the re-dispatched job can claim it — a stale
    // `processing` row is indistinguishable from a live one otherwise.
    expect($abandoned->fresh()->status)->toBe(TriggerEventStatus::Pending);

    Queue::assertPushed(ProcessTriggerEvent::class, fn (ProcessTriggerEvent $job): bool => $job->eventId === $abandoned->id);
});

it('leaves events inside their grace period alone', function () {
    Queue::fake();
    $trigger = reconcileTrigger();
    TriggerEvent::factory()->pending()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subMinute(),
    ]);

    $this->artisan('triggers:reconcile')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('leaves terminal events alone', function () {
    Queue::fake();
    $trigger = reconcileTrigger();
    TriggerEvent::factory()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subDay(),
    ]);
    TriggerEvent::factory()->failed()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('triggers:reconcile')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('skips stranded events whose trigger was deactivated while they waited', function () {
    Queue::fake();
    $trigger = reconcileTrigger(['is_active' => false]);
    $stranded = TriggerEvent::factory()->pending()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subHour(),
    ]);

    $this->artisan('triggers:reconcile')->assertSuccessful();

    expect($stranded->fresh()->status)->toBe(TriggerEventStatus::Skipped);

    Queue::assertNothingPushed();
});

it('does not start a second run when a recovered event is re-processed', function () {
    $trigger = reconcileTrigger();
    $event = TriggerEvent::factory()->create([
        'trigger_id' => $trigger->id,
        'created_at' => now()->subHour(),
    ]);

    // A terminal event that somehow gets re-dispatched must be a no-op — this is
    // the guard that makes at-least-once delivery safe to build on.
    (new ProcessTriggerEvent($event->id, $trigger->id))->handle(app(TriggerFiringService::class));

    expect(Run::query()->count())->toBe(0);
});
