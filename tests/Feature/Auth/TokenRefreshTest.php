<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('exchanges a refresh token for a new token pair', function () {
    User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Password123!',
    ])->json('data');

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $login['refresh_token'],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in']]);

    expect($response->json('data.access_token'))->not->toBe($login['access_token']);
});

it('rejects an invalid refresh token', function () {
    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => 'not-a-real-token',
    ]);

    $response->assertUnprocessable()->assertJsonPath('success', false);
});
