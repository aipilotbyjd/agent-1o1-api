<?php

namespace App\Jobs\Triggers;

use App\Models\Credentials\Credential;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerEventRecorder;
use App\Services\Triggers\TriggerFiringService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class PollTrigger implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    // Slightly over $timeout: a slow poll can still be mid-flight when the next
    // triggers:queue-due-polling tick fires — this keeps a second job for the same
    // trigger from queuing (and racing on poll_cursor) until this one finishes.
    public int $uniqueFor = 120;

    public function __construct(public int $triggerId) {}

    public function uniqueId(): string
    {
        return (string) $this->triggerId;
    }

    public function handle(TriggerFiringService $firing, TriggerEventRecorder $recorder): void
    {
        $trigger = Trigger::with(['triggerType', 'credential'])->find($this->triggerId);

        if ($trigger === null || ! $trigger->is_active) {
            return;
        }

        $preset = $trigger->triggerType?->preset_config ?? [];
        $pollUrl = $preset['poll_url'] ?? null;
        $itemsPath = $preset['items_path'] ?? null;
        $cursorPath = $preset['cursor_path'] ?? null;

        if ($pollUrl === null || $itemsPath === null || $cursorPath === null) {
            return;
        }

        try {
            $response = $this->applyCredential(Http::timeout(15), $trigger)->get($pollUrl);
            $response->throw();
        } catch (Throwable $e) {
            $trigger->registerFailure();
            $recorder->record($trigger, 'poll', false, error: $e->getMessage());
            report($e);

            return;
        }

        $items = data_get($response->json(), $itemsPath, []);
        $lastCursor = $trigger->poll_cursor['value'] ?? null;
        $maxCursor = $lastCursor;

        foreach ($items as $item) {
            $cursorValue = data_get($item, $cursorPath);

            if (! $this->isNewerThan($cursorValue, $lastCursor)) {
                continue;
            }

            // Stop at the first skip so the cursor never advances past an
            // item that still needs to be retried on the next poll tick.
            if ($firing->hasInFlightRun($trigger)) {
                break;
            }

            try {
                $run = $firing->fire($trigger, is_array($item) ? $item : ['value' => $item], 'poll');
            } catch (Throwable $e) {
                $recorder->record($trigger, 'poll', false, error: $e->getMessage());
                report($e);

                break;
            }

            $recorder->record($trigger, 'poll', true, run: $run);

            $maxCursor = $this->isNewerThan($cursorValue, $maxCursor) ? $cursorValue : $maxCursor;
        }

        // Always stamp last_run_at on a completed poll attempt (even with no new
        // items) so QueueDuePollingTriggersCommand's interval due-check advances correctly —
        // it's left untouched on a failed attempt so the next tick retries sooner.
        $trigger->update([
            'last_run_at' => now(),
            'poll_cursor' => $maxCursor !== $lastCursor ? ['value' => $maxCursor] : $trigger->poll_cursor,
        ]);
    }

    private function isNewerThan(mixed $value, mixed $baseline): bool
    {
        if ($baseline === null) {
            return $value !== null;
        }

        if (is_numeric($value) && is_numeric($baseline)) {
            return (float) $value > (float) $baseline;
        }

        return (string) $value !== (string) $baseline;
    }

    private function applyCredential(PendingRequest $request, Trigger $trigger): PendingRequest
    {
        $credential = $trigger->credential;

        if ($credential === null) {
            return $request;
        }

        if ($credential->isExpired()) {
            throw new InvalidArgumentException("Credential [{$credential->name}] has expired.");
        }

        $credential->touchLastUsed();
        $data = $credential->data ?? [];

        return match ($credential->type) {
            Credential::TYPE_BEARER_TOKEN => $request->withToken((string) ($data['token'] ?? '')),
            Credential::TYPE_BASIC_AUTH => $request->withBasicAuth(
                (string) ($data['username'] ?? ''),
                (string) ($data['password'] ?? ''),
            ),
            Credential::TYPE_API_KEY => $request->withHeaders([
                (string) ($data['header'] ?? 'Authorization') => (string) ($data['value'] ?? ''),
            ]),
            default => $request,
        };
    }
}
