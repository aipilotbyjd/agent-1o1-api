<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class StripeCreateProductNode extends StripeNode
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

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL.'/products', [
                'name' => $config['name'],
            ]));

        return $data;
    }
}
