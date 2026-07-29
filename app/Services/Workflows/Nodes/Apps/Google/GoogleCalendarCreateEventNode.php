<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleCalendarCreateEventNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function type(): string
    {
        return 'google_calendar.create_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Create Event';
    }

    public function description(): string
    {
        return 'Create a new calendar event.';
    }

    public function icon(): string
    {
        return 'plus-circle';
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
        return 'https://developers.google.com/calendar/api/v3/reference/events/insert';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'title', 'start', 'end'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'start' => ['type' => 'string'],
                'end' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
                'attendees' => ['type' => 'array'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object'];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $calendarId = $config['calendar_id'] ?? 'primary';
        $timezone = $config['timezone'] ?? 'UTC';

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->post(self::BASE_URL."/calendars/{$calendarId}/events", [
                'summary' => $config['title'],
                'description' => $config['description'] ?? '',
                'start' => ['dateTime' => $config['start'], 'timeZone' => $timezone],
                'end' => ['dateTime' => $config['end'], 'timeZone' => $timezone],
                'attendees' => array_map(fn ($e) => ['email' => $e], (array) ($config['attendees'] ?? [])),
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleCalendar create_event failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
