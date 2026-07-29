<?php

namespace App\Services\Workflows\Nodes\Concerns;

use App\Models\Credentials\Credential;
use App\Models\Runs\Run;
use InvalidArgumentException;

/**
 * Credential lookup for nodes that authenticate against an external system.
 *
 * A credential is always resolved through the run's workspace, so a step can never
 * reach a credential belonging to another tenant by guessing its id.
 */
trait ResolvesCredentials
{
    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException when the step points at a credential that is gone.
     */
    protected function requireCredential(Run $run, array $config): Credential
    {
        $credential = $this->optionalCredential($run, $config);

        if ($credential === null) {
            throw new InvalidArgumentException('This step references a credential that no longer exists.');
        }

        return $credential;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function optionalCredential(Run $run, array $config): ?Credential
    {
        if (($config['credential_id'] ?? null) === null) {
            return null;
        }

        return Credential::query()
            ->where('workspace_id', $run->workspace_id)
            ->find($config['credential_id']);
    }
}
