<?php

use App\Jobs\Triggers\PollTrigger;
use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerType;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Triggers\TriggerEventRecorder;
use App\Services\Triggers\TriggerFiringService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function pollingWorkflow(): Workflow
{
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->published()->create(['workspace_id' => $workspace->id]);
    $workflow->steps()->create(['key' => 'noop', 'type' => 'transform', 'config' => ['mapping' => ['ok' => '1']]]);
    $workflow->publishVersion();

    return $workflow;
}

it('fires a run for each new item and stores the cursor', function () {
    $workflow = pollingWorkflow();
    $type = TriggerType::factory()->polling()->create();
    $credential = Credential::factory()->create([
        'workspace_id' => $workflow->workspace_id,
        'type' => Credential::TYPE_BEARER_TOKEN,
        'data' => ['token' => 'secret-token'],
    ]);
    $trigger = Trigger::factory()->polling()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
        'credential_id' => $credential->id,
    ]);

    Http::fakeSequence('example.test/*')
        ->push(['items' => [
            ['id' => 1, 'title' => 'first'],
            ['id' => 2, 'title' => 'second'],
        ]])
        ->push(['items' => [
            ['id' => 1, 'title' => 'first'],
            ['id' => 2, 'title' => 'second'],
            ['id' => 3, 'title' => 'third'],
        ]]);

    (new PollTrigger($trigger->id))->handle(app(TriggerFiringService::class), app(TriggerEventRecorder::class));

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
    expect(Run::query()->count())->toBe(2)
        ->and($trigger->fresh()->poll_cursor)->toBe(['value' => 2]);

    (new PollTrigger($trigger->id))->handle(app(TriggerFiringService::class), app(TriggerEventRecorder::class));

    expect(Run::query()->count())->toBe(3)
        ->and($trigger->fresh()->poll_cursor)->toBe(['value' => 3]);
});

it('dispatches poll jobs only for triggers whose interval has elapsed', function () {
    Queue::fake();
    $workflow = pollingWorkflow();
    $type = TriggerType::factory()->polling()->create();

    $due = Trigger::factory()->polling()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
        'last_run_at' => now()->subMinutes(10),
    ]);
    $notDue = Trigger::factory()->polling()->create([
        'workspace_id' => $workflow->workspace_id,
        'triggerable_id' => $workflow->id,
        'trigger_type_id' => $type->id,
        'last_run_at' => now(),
    ]);

    $this->artisan('triggers:queue-due-polling')->assertSuccessful();

    Queue::assertPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->triggerId === $due->id);
    Queue::assertNotPushed(PollTrigger::class, fn (PollTrigger $job): bool => $job->triggerId === $notDue->id);
});
