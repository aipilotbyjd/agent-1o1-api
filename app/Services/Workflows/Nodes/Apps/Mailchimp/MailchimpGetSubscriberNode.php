<?php

namespace App\Services\Workflows\Nodes\Apps\Mailchimp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MailchimpGetSubscriberNode extends AppNode
{
    public function type(): string
    {
        return 'mailchimp.get_subscriber';
    }

    public function name(): string
    {
        return 'Mailchimp: Get Subscriber';
    }

    public function description(): string
    {
        return 'Get a subscriber\'s details from an audience list.';
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function color(): string
    {
        return '#ffbe00';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function docsUrl(): ?string
    {
        return 'https://mailchimp.com/developer/marketing/api/list-members/get-member/';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'list_id' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ], 'required' => ['list_id', 'email']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $hash = md5(strtolower($config['email']));

        try {
            $response = $this->mcHttp($credential)->get("/lists/{$config['list_id']}/members/{$hash}");
            if (! $response->successful()) {
                throw new \RuntimeException('Mailchimp get_subscriber failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (\Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function mcHttp(Credential $credential): PendingRequest
    {
        $dc = $credential->data['server_prefix'] ?? 'us1';

        return Http::acceptJson()->asJson()->baseUrl("https://{$dc}.api.mailchimp.com/3.0")->withBasicAuth('anystring', $credential->data['api_key'] ?? '');
    }
}
