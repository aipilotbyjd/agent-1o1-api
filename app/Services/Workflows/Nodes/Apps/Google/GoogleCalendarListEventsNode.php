<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleCalendarListEventsNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function type(): string
    {
        return 'google_calendar.list_events';
    }

    public function name(): string
    {
        return 'Google Calendar: List Events';
    }

    public function description(): string
    {
        return 'List upcoming events from a calendar.';
    }

    public function icon(): string
    {
        return 'calendar';
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
        return 'https://developers.google.com/calendar/api/v3/reference/events/list';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'calendar_id' => ['type' => 'string'],
                'time_min' => ['type' => 'string'],
                'max_results' => ['type' => 'integer'],
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

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL."/calendars/{$calendarId}/events", [
                'timeMin' => $config['time_min'] ?? now()->toRfc3339String(),
                'maxResults' => $config['max_results'] ?? 10,
                'singleEvents' => true,
                'orderBy' => 'startTime',
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleCalendar list_events failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
