<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\ClientRepository;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withPassportClients();
    }

    /**
     * RefreshDatabase wipes oauth_clients between tests, and Passport hashes client
     * secrets at rest, so a fixed .env secret can never match a freshly-seeded client.
     * Create a password-grant client per test and point config at its known plaintext
     * secret instead.
     */
    private function withPassportClients(): void
    {
        $clients = app(ClientRepository::class);

        $password = $clients->createPasswordGrantClient('Testing Password Grant Client', confidential: true);
        $clients->createPersonalAccessGrantClient('Testing Personal Access Client');

        config([
            'passport.password_client_id' => $password->id,
            'passport.password_client_secret' => $password->plainSecret,
        ]);
    }
}
