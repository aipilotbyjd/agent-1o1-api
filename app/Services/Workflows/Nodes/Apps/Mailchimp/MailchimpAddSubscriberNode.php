<?php

namespace App\Services\Workflows\Nodes\Apps\Mailchimp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MailchimpAddSubscriberNode extends AppNode
{
    public function type(): string
    {
        return 'mailchimp.add_subscriber';
    }

    public function name(): string
    {
        return 'Mailchimp: Add Subscriber';
    }

    public function description(): string
    {
        return 'Add or subscribe a member to a Mailchimp audience list.';
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
        return 'https://mailchimp.com/developer/marketing/api/list-members/add-member/';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list_id' => ['type' => 'string', 'description' => 'The audience list ID'],
                'email' => ['type' => 'string', 'format' => 'email', 'description' => 'The subscriber email address'],
                'status' => ['type' => 'string', 'enum' => ['subscribed', 'pending', 'unsubscribed', 'cleaned'], 'default' => 'subscribed'],
                'merge_fields' => ['type' => 'object', 'description' => 'Merge fields (e.g., FNAME, LNAME)', 'default' => null],
            ],
            'required' => ['list_id', 'email'],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'email_address' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        try {
            $response = $this->mcHttp($credential)
                ->post("/lists/{$config['list_id']}/members", [
                    'email_address' => $config['email'],
                    'status' => $config['status'] ?? 'subscribed',
                    'merge_fields' => $config['merge_fields'] ?? new \stdClass,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Mailchimp add_subscriber failed: '.$response->body());
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

        return Http::acceptJson()->asJson()
            ->baseUrl("https://{$dc}.api.mailchimp.com/3.0")
            ->withBasicAuth('anystring', $credential->data['api_key'] ?? '');
    }
}
