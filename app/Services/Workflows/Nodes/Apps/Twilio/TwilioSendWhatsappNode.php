<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TwilioSendWhatsappNode extends AppNode
{
    public function type(): string
    {
        return 'twilio.send_whatsapp';
    }

    public function name(): string
    {
        return 'Twilio: Send WhatsApp';
    }

    public function description(): string
    {
        return 'Send a WhatsApp message via Twilio.';
    }

    public function icon(): string
    {
        return 'message-circle';
    }

    public function color(): string
    {
        return '#f22f46';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_BASIC_AUTH;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'to', 'body'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'from' => ['type' => 'string'],
                'body' => ['type' => 'string'],
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

        $sid = $credential->data['account_sid'] ?? '';
        $authToken = $credential->data['auth_token'] ?? $credential->data['password'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($sid, $authToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => 'whatsapp:'.$config['to'],
                'From' => 'whatsapp:'.($config['from'] ?? ($credential->data['from_number'] ?? '')),
                'Body' => $config['body'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Twilio send_whatsapp failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
