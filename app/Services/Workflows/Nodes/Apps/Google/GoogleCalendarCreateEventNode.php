<?php

namespace App\Services\Workflows\Nodes\Apps\Google;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class GoogleCalendarCreateEventNode extends HttpAppNode
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

        $calendarId = $config['calendar_id'] ?? 'primary';
        $timezone = $config['timezone'] ?? 'UTC';

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL."/calendars/{$calendarId}/events", [
                'summary' => $config['title'],
                'description' => $config['description'] ?? '',
                'start' => ['dateTime' => $config['start'], 'timeZone' => $timezone],
                'end' => ['dateTime' => $config['end'], 'timeZone' => $timezone],
                'attendees' => array_map(fn ($e) => ['email' => $e], (array) ($config['attendees'] ?? [])),
            ]));

        return $data;
    }
}
