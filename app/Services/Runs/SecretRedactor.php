<?php

namespace App\Services\Runs;

use App\Models\Credentials\Credential;
use App\Models\Variable;

class SecretRedactor
{
    public const MASK = '[redacted]';

    /**
     * Secret values per workspace, memoized for the lifetime of the request/job so a
     * run that logs many lines decrypts each credential once rather than per line.
     *
     * @var array<int, array<int, string>>
     */
    private array $secrets = [];

    /**
     * Replace every one of the workspace's secret values found anywhere in the given
     * structure. Run logs and step errors are assembled from exception messages and
     * upstream response bodies, either of which can echo a token back verbatim.
     */
    public function redact(int $workspaceId, mixed $value): mixed
    {
        $secrets = $this->secretsFor($workspaceId);

        if ($secrets === []) {
            return $value;
        }

        return $this->walk($value, $secrets);
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function walk(mixed $value, array $secrets): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->walk($item, $secrets), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return str_replace($secrets, self::MASK, $value);
    }

    /**
     * Every secret string worth masking for a workspace: the values of secret-flagged
     * variables plus every value stored on its credentials.
     *
     * @return array<int, string>
     */
    private function secretsFor(int $workspaceId): array
    {
        if (isset($this->secrets[$workspaceId])) {
            return $this->secrets[$workspaceId];
        }

        $values = Variable::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_secret', true)
            ->pluck('value')
            ->all();

        Credential::query()
            ->where('workspace_id', $workspaceId)
            ->get()
            ->each(function (Credential $credential) use (&$values): void {
                foreach ($credential->data ?? [] as $item) {
                    if (is_string($item)) {
                        $values[] = $item;
                    }
                }
            });

        // Short values would mask harmless substrings ("1", "on") all over a log line.
        $values = array_values(array_unique(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value) && mb_strlen($value) >= 6,
        )));

        return $this->secrets[$workspaceId] = $values;
    }
}
