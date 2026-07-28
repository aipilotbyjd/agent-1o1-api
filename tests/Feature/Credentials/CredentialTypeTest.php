<?php

use App\Models\Credentials\CredentialType;
use App\Models\User;

it('lists active credential types', function () {
    $user = User::factory()->create();
    CredentialType::factory()->create(['is_active' => true, 'sort_order' => 1]);
    CredentialType::factory()->create(['is_active' => false, 'sort_order' => 0]);

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/credential-types');

    $response->assertOk()->assertJsonCount(1, 'data');
});
