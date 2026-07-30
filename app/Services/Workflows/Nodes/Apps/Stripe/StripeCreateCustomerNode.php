<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class StripeCreateCustomerNode extends StripeNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function type(): string
    {
        return 'stripe.create_customer';
    }

    public function name(): string
    {
        return 'Stripe: Create Customer';
    }

    public function description(): string
    {
        return 'Create a new customer in Stripe.';
    }

    public function icon(): string
    {
        return 'user-plus';
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
            'required' => ['credential_id', 'email'],
            'properties' => [
                'credential_id' => ['type' => 'integer'],
                'email' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'metadata' => ['type' => 'object'],
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $credential = $this->requireCredential($run, $config);

        $data = $this->send($run, $credential, fn (PendingRequest $http): Response => $http
            ->post(self::BASE_URL.'/customers', [
                'email' => $config['email'],
                'name' => $config['name'] ?? null,
                'metadata' => $config['metadata'] ?? [],
            ]));

        return $data;
    }
}
