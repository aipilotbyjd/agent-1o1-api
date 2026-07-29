<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class StripeCreateSubscriptionNode extends AppNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_subscription';
    }

    public function name(): string
    {
        return 'Stripe: Create Subscription';
    }

    public function description(): string
    {
        return 'Create a new subscription for a customer.';
    }

    public function icon(): string
    {
        return 'repeat';
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
            'required' => ['credential_id', 'customer_id', 'price_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'customer_id' => ['type' => 'string'],
                'price_id' => ['type' => 'string'],
                'trial_days' => ['type' => 'integer'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'status' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);
        $startedAt = microtime(true);

        $apiKey = $credential->data['secret_key'] ?? $credential->data['api_key'] ?? '';

        $response = Http::timeout(15)
            ->withBasicAuth($apiKey, '')
            ->asForm()
            ->post(self::BASE_URL.'/subscriptions', [
                'customer' => $config['customer_id'],
                'items' => [['price' => $config['price_id']]],
                'trial_period_days' => $config['trial_days'] ?? null,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Stripe create_subscription failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
