<?php

namespace App\Services\Workflows\Nodes\Apps\Mailchimp;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MailchimpAddTagNode extends AppNode
{
    public function type(): string
    {
        return 'mailchimp.add_tag';
    }

    public function name(): string
    {
        return 'Mailchimp: Add Tag';
    }

    public function description(): string
    {
        return 'Add a tag to a subscriber.';
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
        return 'https://mailchimp.com/developer/marketing/api/list-member-tags/add-member-tag/';
    }

    public function configSchema(): array
    {
        return ['type' => 'object', 'properties' => ['list_id' => ['type' => 'string'], 'email' => ['type' => 'string'], 'tag' => ['type' => 'string']], 'required' => ['list_id', 'email', 'tag']];
    }

    public function outputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['tagged' => ['type' => 'boolean']]];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);
        $hash = md5(strtolower($config['email']));
        try {
            $response = $this->mcHttp($credential)->post("/lists/{$config['list_id']}/members/{$hash}/tags", ['tags' => [['name' => $config['tag'], 'status' => 'active']]]);
            if (! $response->successful()) {
                throw new RuntimeException('Mailchimp add_tag failed: '.$response->body());
            }
            $this->recordMetric($run, true, $startedAt);

            return ['tagged' => true];
        } catch (Throwable $e) {
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
