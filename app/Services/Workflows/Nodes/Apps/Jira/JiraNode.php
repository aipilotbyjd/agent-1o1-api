<?php

namespace App\Services\Workflows\Nodes\Apps\Jira;

use App\Models\Credentials\Credential;
use App\Services\Workflows\Nodes\Apps\HttpAppNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared authentication and host resolution for the Jira connectors.
 *
 * Jira is per-tenant, so the host comes out of the credential rather than a constant,
 * and it authenticates with the account email plus an API token.
 */
abstract class JiraNode extends HttpAppNode
{
    protected function client(Credential $credential): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withBasicAuth(
                $credential->data['email'] ?? '',
                $credential->data['api_token'] ?? $credential->data['password'] ?? '',
            );
    }

    /**
     * The tenant's REST base, e.g. `https://acme.atlassian.net/rest/api/3`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function baseUrl(Credential $credential, array $config): string
    {
        $domain = $credential->data['domain'] ?? $config['domain'] ?? '';

        return "https://{$domain}/rest/api/3";
    }
}
