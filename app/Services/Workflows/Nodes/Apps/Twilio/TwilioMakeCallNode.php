<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TwilioMakeCallNode extends AppNode
{
    public function type(): string
    {
        return 'twilio.make_call';
    }

    public function name(): string
    {
        return 'Twilio: Make Call';
    }

    public function description(): string
    {
        return 'Make a phone call via Twilio.';
    }

    public function icon(): string
    {
        return 'phone';
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
            'required' => ['credential_id', 'to'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'from' => ['type' => 'string'],
                'twiml_url' => ['type' => 'string'],
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
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json", [
                'To' => $config['to'],
                'From' => $config['from'] ?? ($credential->data['from_number'] ?? ''),
                'Url' => $config['twiml_url'] ?? 'http://demo.twilio.com/docs/voice.xml',
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Twilio make_call failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
