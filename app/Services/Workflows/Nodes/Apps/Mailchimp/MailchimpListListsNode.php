<?php

namespace App\Services\Workflows\Nodes\Apps\Mailchimp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MailchimpListListsNode extends AppNode
{
    public function type(): string
    {
        return 'mailchimp.list_lists';
    }

    public function name(): string
    {
        return 'Mailchimp: List Audiences';
    }

    public function description(): string
    {
        return 'List all audience lists in the account.';
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
        return 'https://mailchimp.com/developer/marketing/api/lists/list-lists/';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['count' => ['type' => 'integer', 'default' => 10]]];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['lists' => ['type' => 'array'], 'total_items' => ['type' => 'integer']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        try {
            $response = $this->mcHttp($credential)->get('/lists', ['count' => $config['count'] ?? 10]);
            if (! $response->successful()) {
                throw new \RuntimeException('Mailchimp list_lists failed: '.$response->body());
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
