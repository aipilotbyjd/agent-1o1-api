<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GoogleCalendarUpdateEventNode extends HttpAppNode
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function type(): string
    {
        return 'google_calendar.update_event';
    }

    public function name(): string
    {
        return 'Google Calendar: Update Event';
    }

    public function description(): string
    {
        return 'Update an existing calendar event.';
    }

    public function icon(): string
    {
        return 'edit';
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
        return 'https://developers.google.com/calendar/api/v3/reference/events/patch';
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
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'start' => ['type' => 'string'],
                'end' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
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

        $calendarId = $config['calendar_id'] ?? 'primary';
        $timezone = $config['timezone'] ?? 'UTC';

        $body = array_filter([
            'summary' => $config['title'] ?? null,
            'description' => $config['description'] ?? null,
        ]);

        if (isset($config['start'])) {
            $body['start'] = ['dateTime' => $config['start'], 'timeZone' => $timezone];
        }
        if (isset($config['end'])) {
            $body['end'] = ['dateTime' => $config['end'], 'timeZone' => $timezone];
        }

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->patch(self::BASE_URL."/calendars/{$calendarId}/events/{$config['event_id']}", $body));

        return $data;
    }
}
