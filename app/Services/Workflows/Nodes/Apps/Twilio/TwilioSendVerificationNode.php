<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class TwilioSendVerificationNode extends AppNode
{
    public function type(): string
    {
        return 'twilio.send_verification';
    }

    public function name(): string
    {
        return 'Twilio: Send Verification';
    }

    public function description(): string
    {
        return 'Send a verification code via SMS or email.';
    }

    public function icon(): string
    {
        return 'shield';
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
                'channel' => ['type' => 'string'],
                'service_sid' => ['type' => 'string'],
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
        $serviceSid = $config['service_sid'] ?? $credential->data['verify_service_sid'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($sid, $authToken)
            ->asForm()
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications", [
                'To' => $config['to'],
                'Channel' => $config['channel'] ?? 'sms',
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Twilio send_verification failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
