<?php

namespace App\Services\Workflows\Nodes\Apps\Twilio;

use App\Models\Credentials\Credential;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared authentication for the Twilio connectors.
 *
 * The account SID is both the basic-auth username and part of every endpoint path, so
 * it is exposed separately as well as being applied to the client.
 */
abstract class TwilioNode extends HttpAppNode
{
    protected function client(Credential $credential): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withBasicAuth(
                $this->accountSid($credential),
                $credential->data['auth_token'] ?? $credential->data['password'] ?? '',
            )
            ->asForm();
    }

    protected function accountSid(Credential $credential): string
    {
        return $credential->data['account_sid'] ?? '';
    }
}
