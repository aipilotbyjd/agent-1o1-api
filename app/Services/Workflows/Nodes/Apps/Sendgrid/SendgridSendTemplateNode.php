<?php

namespace App\Services\Workflows\Nodes\Apps\Sendgrid;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendgridSendTemplateNode extends AppNode
{
    private const BASE_URL = 'https://api.sendgrid.com/v3';

    public function type(): string
    {
        return 'sendgrid.send_template';
    }

    public function name(): string
    {
        return 'Sendgrid: Send Template';
    }

    public function description(): string
    {
        return 'Send an email using a Sendgrid dynamic template.';
    }

    public function icon(): string
    {
        return 'layout';
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
            'required' => ['credential_id', 'to', 'from', 'template_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'from' => ['type' => 'string'],
                'template_id' => ['type' => 'string'],
                'template_data' => ['type' => 'object'],
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
                    'dynamic_template_data' => $config['template_data'] ?? [],
                ]],
                'from' => ['email' => $config['from']],
                'template_id' => $config['template_id'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Sendgrid send_template failed: '.$response->body());
        }

        return ['sent' => true];
    }
}
