<?php

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials', function () {
    User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'user']]);
});

it('rejects login with an invalid password', function () {
    User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()->assertJsonPath('success', false);
});

it('rejects login for a nonexistent user', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()->assertJsonPath('success', false);
});

it('returns a two-factor challenge instead of tokens when 2FA is enabled', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);
    app(TwoFactorAuthService::class)->enable($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['challenge_token']])
        ->assertJsonMissingPath('data.access_token');
});
