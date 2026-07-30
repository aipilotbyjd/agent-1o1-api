<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class TwilioSendVerificationNode extends TwilioNode
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

        $sid = $credential->data['account_sid'] ?? '';
        $serviceSid = $config['service_sid'] ?? $credential->data['verify_service_sid'] ?? '';

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications", [
                'To' => $config['to'],
                'Channel' => $config['channel'] ?? 'sms',
            ]));

        return $data;
    }
}
