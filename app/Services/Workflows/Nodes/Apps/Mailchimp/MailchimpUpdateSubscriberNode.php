<?php

namespace App\Services\Workflows\Nodes\Apps\Mailchimp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MailchimpUpdateSubscriberNode extends AppNode
{
    public function type(): string
    {
        return 'mailchimp.update_subscriber';
    }

    public function name(): string
    {
        return 'Mailchimp: Update Subscriber';
    }

    public function description(): string
    {
        return 'Update a subscriber\'s status or merge fields.';
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
        return 'https://mailchimp.com/developer/marketing/api/list-members/update-member/';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list_id' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['subscribed', 'pending', 'unsubscribed', 'cleaned']],
                'merge_fields' => ['type' => 'object'],
            ],
            'required' => ['list_id', 'email'],
        ];
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
            $response = $this->mcHttp($credential)
                ->patch("/lists/{$config['list_id']}/members/{$hash}", array_filter([
                    'status' => $config['status'] ?? null,
                    'merge_fields' => $config['merge_fields'] ?? null,
                ]));

            if (! $response->successful()) {
                throw new RuntimeException('Mailchimp update_subscriber failed: '.$response->body());
            }

            $this->recordMetric($run, true, $startedAt);

            return $response->json();
        } catch (Throwable $e) {
            $this->recordMetric($run, false, $startedAt);
            throw $e;
        }
    }

    private function mcHttp(Credential $credential): PendingRequest
    {
        $dc = $credential->data['server_prefix'] ?? 'us1';

        return Http::acceptJson()->asJson()
            ->baseUrl("https://{$dc}.api.mailchimp.com/3.0")
            ->withBasicAuth('anystring', $credential->data['api_key'] ?? '');
    }
}
