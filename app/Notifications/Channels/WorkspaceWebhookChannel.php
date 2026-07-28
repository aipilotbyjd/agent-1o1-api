<?php

namespace App\Notifications\Channels;

use App\Models\Notifications\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceWebhookChannel
{
    /**
     * Deliver the notification to every active workspace channel it targets.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWorkspaceChannel')) {
            return;
        }

        $payload = $notification->toWorkspaceChannel($notifiable);
        $channelIds = $payload['channel_ids'] ?? [];

        if ($channelIds === []) {
            return;
        }

        $channels = NotificationChannel::query()
            ->whereIn('id', $channelIds)
            ->where('is_active', true)
            ->get();

        foreach ($channels as $channel) {
            $this->deliver($channel, $payload['message']);
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function deliverTest(NotificationChannel $channel): array
    {
        return $this->deliver($channel, 'This is a test notification from your workspace.');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function deliver(NotificationChannel $channel, string $message): array
    {
        try {
            $config = $channel->config;

            $response = match ($channel->type) {
                'discord' => Http::post($config['url'], ['content' => $message]),
                'slack' => Http::post($config['url'], ['text' => $message]),
                'webhook' => Http::withHeaders($config['headers'] ?? [])->post($config['url'], ['message' => $message]),
                default => null,
            };

            if ($response === null) {
                return ['ok' => false, 'message' => "Unsupported channel type: {$channel->type}."];
            }

            if (! $response->successful()) {
                Log::warning('Workspace notification channel delivery failed.', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                ]);

                return ['ok' => false, 'message' => "Delivery failed: HTTP {$response->status()}."];
            }

            return ['ok' => true, 'message' => 'Delivered.'];
        } catch (Throwable $e) {
            Log::warning('Workspace notification channel delivery errored.', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => "Delivery error: {$e->getMessage()}"];
        }
    }
}
