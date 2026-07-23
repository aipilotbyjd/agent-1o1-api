<?php

use App\Models\User;

it('lists active sessions with the current one flagged', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('device-a');
    $user->createToken('device-b');

    $response = $this->withToken($tokenA->accessToken)->getJson('/api/v1/auth/sessions');

    $response->assertOk()->assertJsonCount(2, 'data');
    $current = collect($response->json('data'))->firstWhere('is_current', true);
    expect($current['id'])->toBe($tokenA->token->id);
});

it('revokes a specific session', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('device-a');
    $tokenB = $user->createToken('device-b');

    $response = $this->withToken($tokenA->accessToken)->deleteJson("/api/v1/auth/sessions/{$tokenB->token->id}");

    $response->assertOk()->assertJsonPath('success', true);
    expect($tokenB->token->fresh()->revoked)->toBeTrue();
    expect($tokenA->token->fresh()->revoked)->toBeFalse();
});

it('rejects revoking another user\'s session', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('test');
    $otherToken = $otherUser->createToken('test');

    $response = $this->withToken($token->accessToken)->deleteJson("/api/v1/auth/sessions/{$otherToken->token->id}");

    $response->assertNotFound();
    expect($otherToken->token->fresh()->revoked)->toBeFalse();
});
