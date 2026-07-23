<?php

use App\Models\User;

it('blocks an unverified user with a clean 403 JSON response', function () {
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->getJson('/api/v1/workflows');

    $response->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Your email address is not verified.');
});

it('allows a verified user through', function () {
    $user = User::factory()->create(); // factory default has email_verified_at set
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->getJson('/api/v1/workflows');

    $response->assertOk()->assertJsonPath('success', true);
});

it('rejects an unauthenticated request with 401, not 403', function () {
    $response = $this->getJson('/api/v1/workflows');

    $response->assertUnauthorized();
});
