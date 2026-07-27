<?php

namespace App\Services\Triggers;

use App\Models\Runs\Run;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TriggerEventRecorder
{
    /**
     * Headers safe to persist for debugging — never store Authorization or cookies.
     *
     * @var array<int, string>
     */
    private const ALLOWED_HEADERS = [
        'X-GitHub-Event', 'X-GitHub-Delivery', 'X-Hub-Signature-256',
        'Stripe-Signature',
        'X-Slack-Retry-Num', 'X-Slack-Signature',
        'Content-Type',
    ];

    public function record(
        Trigger $trigger,
        string $source,
        bool $matched,
        ?Run $run = null,
        ?Request $request = null,
        ?string $deliveryId = null,
        ?string $error = null,
    ): TriggerEvent {
        return TriggerEvent::create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'matched' => $matched,
            'run_id' => $run?->id,
            'payload_snippet' => $request !== null ? Str::limit($request->getContent(), 5000) : null,
            'headers' => $request !== null ? $this->allowedHeaders($request) : null,
            'error' => $error,
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function allowedHeaders(Request $request): array
    {
        $headers = [];

        foreach (self::ALLOWED_HEADERS as $name) {
            $value = $request->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
