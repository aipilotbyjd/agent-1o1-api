<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use App\Services\Workflows\Nodes\Apps\AppNode;
use Illuminate\Support\Facades\Http;

class StripeCreateInvoiceNode extends AppNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_invoice';
    }

    public function name(): string
    {
        return 'Stripe: Create Invoice';
    }

    public function description(): string
    {
        return 'Create a new invoice for a customer.';
    }

    public function icon(): string
    {
        return 'file-text';
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
            'required' => ['credential_id', 'customer_id'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'customer_id' => ['type' => 'string'],
                'auto_advance' => ['type' => 'boolean'],
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
            ->post(self::BASE_URL.'/invoices', [
                'customer' => $config['customer_id'],
                'auto_advance' => $config['auto_advance'] ?? true,
            ]);

        $ok = $response->successful();
        $this->recordMetric($run, $ok, $startedAt);

        if (! $ok) {
            throw new \RuntimeException('Stripe create_invoice failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
