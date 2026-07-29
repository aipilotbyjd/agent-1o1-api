<?php

namespace App\Services\Workflows\Nodes\Apps\Sendgrid;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class SendgridSendEmailNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    public function type(): string
    {
        return 'sendgrid.send_email';
    }

    public function name(): string
    {
        return 'Sendgrid: Send Email';
    }

    public function description(): string
    {
        return 'Send a transactional email via Sendgrid.';
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function color(): string
    {
        return '#1a82e2';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://docs.sendgrid.com/api-reference/mail-send/mail-send';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'to', 'from', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'from' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'is_html' => ['type' => 'boolean'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sent' => ['type' => 'boolean'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['api_key'] ?? $credential->data['token'] ?? '';

        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->post(self::BASE_URL.'/mail/send', [
                'personalizations' => [[
                    'to' => [['email' => $config['to']]],
                    'subject' => $config['subject'] ?? '',
                ]],
                'from' => ['email' => $config['from']],
                'content' => [[
                    'type' => ($config['is_html'] ?? false) ? 'text/html' : 'text/plain',
                    'value' => $config['body'],
                ]],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Sendgrid send_email failed: '.$response->body());
        }

        return ['sent' => true];
    }
}
