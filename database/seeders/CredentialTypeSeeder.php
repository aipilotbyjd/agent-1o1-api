<?php

namespace Database\Seeders;

use App\Models\Credentials\CredentialType;
use Illuminate\Database\Seeder;

class CredentialTypeSeeder extends Seeder
{
    /**
     * Seed the builtin credential type catalog. Every auth_type here has matching
     * support in HttpToolHandler for applying the stored data to an outbound request.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog() as $type) {
            CredentialType::updateOrCreate(
                ['key' => $type['key']],
                [...$type, 'is_active' => true, 'sort_order' => $sort++],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            ['key' => 'api_key', 'name' => 'API Key', 'auth_type' => 'api_key',
                'description' => 'Sends a static key in a request header.',
                'color' => '#6366f1', 'icon' => 'key', 'docs_url' => null,
                'fields' => [
                    ['name' => 'header', 'label' => 'Header name', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'value', 'label' => 'API key', 'type' => 'string', 'secret' => true, 'required' => true],
                ]],
            ['key' => 'bearer_token', 'name' => 'Bearer Token', 'auth_type' => 'bearer_token',
                'description' => 'Sends an Authorization: Bearer header.',
                'color' => '#0ea5e9', 'icon' => 'shield', 'docs_url' => null,
                'fields' => [
                    ['name' => 'token', 'label' => 'Token', 'type' => 'string', 'secret' => true, 'required' => true],
                ]],
            ['key' => 'basic_auth', 'name' => 'Basic Auth', 'auth_type' => 'basic_auth',
                'description' => 'Sends HTTP Basic authentication.',
                'color' => '#f59e0b', 'icon' => 'lock', 'docs_url' => null,
                'fields' => [
                    ['name' => 'username', 'label' => 'Username', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'string', 'secret' => true, 'required' => true],
                ]],
            ['key' => 'database', 'name' => 'Database', 'auth_type' => 'database',
                'description' => 'Connection details for an external SQL database.',
                'color' => '#10b981', 'icon' => 'database', 'docs_url' => null,
                'fields' => [
                    ['name' => 'host', 'label' => 'Host', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'port', 'label' => 'Port', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'database', 'label' => 'Database', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'username', 'label' => 'Username', 'type' => 'string', 'secret' => false, 'required' => true],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'string', 'secret' => true, 'required' => true],
                ]],
        ];
    }
}
