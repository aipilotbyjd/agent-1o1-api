<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class StripeCreatePriceNode extends StripeNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_price';
    }

    public function name(): string
    {
        return 'Stripe: Create Price';
    }

    public function description(): string
    {
        return 'Create a new price for a product.';
    }

    public function icon(): string
    {
        return 'tag';
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
            'required' => ['credential_id', 'product_id', 'amount', 'currency'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'product_id' => ['type' => 'string'],
                'amount' => ['type' => 'integer'],
                'currency' => ['type' => 'string'],
                'interval' => ['type' => 'string'],
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

        $body = [
            'product' => $config['product_id'],
            'unit_amount' => $config['amount'],
            'currency' => $config['currency'],
        ];

        if (! empty($config['interval'])) {
            $body['recurring'] = ['interval' => $config['interval']];
        }

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL.'/prices', $body));

        return $data;
    }
}
