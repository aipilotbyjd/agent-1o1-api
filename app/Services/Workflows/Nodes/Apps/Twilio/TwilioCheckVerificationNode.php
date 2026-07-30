<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioCheckVerificationNode extends AppNode
{
    public function type(): string
    {
        return 'twilio.check_verification';
    }

    public function name(): string
    {
        return 'Twilio: Check Verification';
    }

    public function description(): string
    {
        return 'Verify a code sent via Twilio Verify.';
    }

    public function icon(): string
    {
        return 'check-circle';
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
            'required' => ['credential_id', 'to', 'code'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'to' => ['type' => 'string'],
                'code' => ['type' => 'string'],
                'service_sid' => ['type' => 'string'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'valid' => ['type' => 'boolean'],
                'status' => ['type' => 'string'],
            ],
        ];
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
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/VerificationCheck", [
                'To' => $config['to'],
                'Code' => $config['code'],
            ]);

        $ok = $response->successful();
        $data = $response->json() ?? [];
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new RuntimeException('Twilio check_verification failed: '.$response->body());
        }

        return ['valid' => ($data['status'] ?? '') === 'approved', 'status' => $data['status'] ?? ''];
    }
}
