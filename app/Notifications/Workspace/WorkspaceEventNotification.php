<?php

namespace App\Notifications\Workspace;

use App\Enums\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\Workspace;
use App\Notifications\Channels\WorkspaceWebhookChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class WorkspaceEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly Workspace $workspace,
        public readonly NotificationEvent $event,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $preference = $this->preferenceFor($notifiable);

        $channels = [];

        if ($preference?->in_app ?? NotificationEvent::DEFAULT_IN_APP) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }

        if ($preference?->email ?? NotificationEvent::DEFAULT_EMAIL) {
            $channels[] = 'mail';
        }

        if (! empty($preference?->channel_ids)) {
            $channels[] = WorkspaceWebhookChannel::class;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'workspace_id' => $this->workspace->id,
            'type' => $this->event->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ];
    }

    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'workspace_id' => $this->workspace->id,
            'type' => $this->event->value,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'created_at' => now()->toISOString(),
        ]);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->workspace->id}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body ?? $this->title);
    }

    /**
     * @return array{workspace_id: int, channel_ids: array<int, int>, message: string}
     */
    public function toWorkspaceChannel(mixed $notifiable): array
    {
        return [
            'workspace_id' => $this->workspace->id,
            'channel_ids' => $this->preferenceFor($notifiable)?->channel_ids ?? [],
            'message' => $this->body ? "{$this->title}: {$this->body}" : $this->title,
        ];
    }

    /**
     * The recipient's saved preference for this event, if they have one.
     */
    private function preferenceFor(mixed $notifiable): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('user_id', $notifiable->id)
            ->where('event_key', $this->event->value)
            ->first();
    }
}
