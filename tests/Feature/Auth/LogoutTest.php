<?php

use App\Models\User;

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->postJson('/api/v1/auth/logout');

    $response->assertOk()->assertJsonPath('success', true);
    expect($token->token->fresh()->revoked)->toBeTrue();
});

it('rejects logout without a token', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized()->assertJsonPath('success', false);
});

it('logs out of all devices and revokes every token', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('device-a');
    $tokenB = $user->createToken('device-b');

    $response = $this->withToken($tokenA->accessToken)->postJson('/api/v1/auth/logout-all');

    $response->assertOk()->assertJsonPath('success', true);
    expect($tokenA->token->fresh()->revoked)->toBeTrue();
    expect($tokenB->token->fresh()->revoked)->toBeTrue();
});
