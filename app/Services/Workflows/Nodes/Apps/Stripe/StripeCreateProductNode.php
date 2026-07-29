<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class StripeCreateProductNode extends AppNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_product';
    }

    public function name(): string
    {
        return 'Stripe: Create Product';
    }

    public function description(): string
    {
        return 'Create a new product in Stripe.';
    }

    public function icon(): string
    {
        return 'package';
    }

    public function color(): string
    {
        return '#635bff';
    }

    public function credentialType(): ?string
    {
        return Credential::TYPE_API_KEY;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['credential_id', 'name'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
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

        $apiKey = $credential->data['secret_key'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($apiKey, '')
            ->asForm()
            ->post(self::BASE_URL.'/products', [
                'name' => $config['name'],
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Stripe create_product failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
