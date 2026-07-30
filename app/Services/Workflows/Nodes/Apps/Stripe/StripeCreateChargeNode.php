<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class StripeCreateChargeNode extends StripeNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_charge';
    }

    public function name(): string
    {
        return 'Stripe: Create Charge';
    }

    public function description(): string
    {
        return 'Create a charge or payment intent in Stripe.';
    }

    public function icon(): string
    {
        return 'credit-card';
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
            'required' => ['credential_id', 'amount'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'amount' => ['type' => 'integer'],
                'currency' => ['type' => 'string'],
                'customer_id' => ['type' => 'string'],
                'payment_method' => ['type' => 'string'],
                'confirm' => ['type' => 'boolean'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'amount' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL.'/payment_intents', [
                'amount' => $config['amount'],
                'currency' => $config['currency'] ?? 'usd',
                'customer' => $config['customer_id'] ?? null,
                'payment_method' => $config['payment_method'] ?? null,
                'confirm' => $config['confirm'] ?? false,
            ]));

        return $data;
    }
}
