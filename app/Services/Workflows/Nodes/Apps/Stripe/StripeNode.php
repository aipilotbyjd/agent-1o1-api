<?php

namespace App\Services\Workflows\Nodes\Apps\Stripe;

use App\Models\Credentials\Credential;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared authentication for the Stripe connectors.
 *
 * Stripe takes the secret key as the basic-auth username with an empty password, and
 * its REST API is form-encoded rather than JSON — decisions that are the same for every
 * endpoint, so they live here rather than in each node.
 */
abstract class StripeNode extends HttpAppNode
{
    protected function client(Credential $credential): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withBasicAuth($credential->data['secret_key'] ?? $credential->data['api_key'] ?? '', '')
            ->asForm();
    }
}
