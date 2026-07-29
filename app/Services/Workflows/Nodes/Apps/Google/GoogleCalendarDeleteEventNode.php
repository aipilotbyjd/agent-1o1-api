<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleCalendarDeleteEventNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function type(): string
    {
        return 'google_calendar.delete_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Delete Event';
    }

    public function description(): string
    {
        return 'Delete a calendar event.';
    }

    public function icon(): string
    {
        return 'trash-2';
    }

    public function color(): string
    {
        return '#4285f4';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BEARER_TOKEN;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.google.com/calendar/api/v3/reference/events/delete';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'event_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'event_id' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'deleted' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $calendarId = $config['calendar_id'] ?? 'primary';

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->delete(self::BASE_URL."/calendars/{$calendarId}/events/{$config['event_id']}");

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleCalendar delete_event failed: '.$response->body());
        }

        return ['deleted' => true];
    }
}
