<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class TwilioSendSmsNode extends TwilioNode
{
    public function type(): string
    {
        return 'twilio.send_sms';
    }

    public function name(): string
    {
        return 'Twilio: Send SMS';
    }

    public function description(): string
    {
        return 'Send an SMS message via Twilio.';
    }

    public function icon(): string
    {
        return 'message-square';
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

        $sid = $credential->data['account_sid'] ?? '';

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $config['to'],
                'From' => $config['from'] ?? ($credential->data['from_number'] ?? ''),
                'Body' => $config['body'],
            ]));

        return $data;
    }
}
