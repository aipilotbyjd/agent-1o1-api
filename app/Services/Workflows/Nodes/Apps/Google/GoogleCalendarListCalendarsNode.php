<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class GoogleCalendarListCalendarsNode extends AppNode
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function type(): string
    {
        return 'google_calendar.list_calendars';
    }

    public function name(): string
    {
        return 'Google Calendar: List Calendars';
    }

    public function description(): string
    {
        return 'List all calendars the user has access to.';
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
        return 'https://developers.google.com/calendar/api/v3/reference/calendarList/list';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
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

        $response = Http::timeout(15)
            ->withToken($credential->data['token'] ?? '')
            ->get(self::BASE_URL.'/users/me/calendarList');

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('GoogleCalendar list_calendars failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
