<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

function authenticateForTwoFactor(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test');

    return [$user, $token->accessToken];
}

it('enables two-factor authentication and returns a secret', function () {
    [, $accessToken] = authenticateForTwoFactor();

    $response = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/enable');

    $response->assertOk()->assertJsonStructure(['data' => ['secret', 'otpauth_url']]);
});

it('confirms two-factor authentication with a valid code and returns recovery codes', function () {
    [$user, $accessToken] = authenticateForTwoFactor();

    $secret = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $response = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);

    $response->assertOk()->assertJsonCount(8, 'data.recovery_codes');
    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('rejects confirmation with an invalid code', function () {
    [, $accessToken] = authenticateForTwoFactor();
    $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/enable');

    $response = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000']);

    $response->assertUnprocessable()->assertJsonPath('success', false);
});

it('disables two-factor authentication with the correct password', function () {
    $user = User::factory()->create(['password' => Hash::make('Password123!')]);
    $token = $user->createToken('test');

    $secret = $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);

    $response = $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/disable', [
        'current_password' => 'Password123!',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('regenerates recovery codes', function () {
    [$user, $accessToken] = authenticateForTwoFactor();
    $secret = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $original = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])->json('data.recovery_codes');

    $response = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/recovery-codes/regenerate');

    $response->assertOk()->assertJsonCount(8, 'data.recovery_codes');
    expect($response->json('data.recovery_codes'))->not->toBe($original);
});

it('reports how many recovery codes remain', function () {
    [, $accessToken] = authenticateForTwoFactor();
    $secret = $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->withToken($accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $code]);

    $response = $this->withToken($accessToken)->getJson('/api/v1/auth/2fa/recovery-codes');

    $response->assertOk()->assertJsonPath('data.recovery_codes_remaining', 8);
});

it('completing the 2FA login challenge returns the same shape as a normal login', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);
    $token = $user->createToken('test');
    $secret = $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $enableCode = app(Google2FA::class)->getCurrentOtp($secret);
    $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $enableCode]);

    $challengeToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Password123!',
    ])->json('data.challenge_token');

    $loginCode = app(Google2FA::class)->getCurrentOtp($secret);

    $response = $this->postJson('/api/v1/auth/2fa/verify', [
        'challenge_token' => $challengeToken,
        'code' => $loginCode,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'user']]);
});

it('rejects reusing the same challenge token twice', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('Password123!')]);
    $token = $user->createToken('test');
    $secret = $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/enable')->json('data.secret');
    $enableCode = app(Google2FA::class)->getCurrentOtp($secret);
    $this->withToken($token->accessToken)->postJson('/api/v1/auth/2fa/confirm', ['code' => $enableCode]);

    $challengeToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Password123!',
    ])->json('data.challenge_token');

    $code = app(Google2FA::class)->getCurrentOtp($secret);
    $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => $challengeToken, 'code' => $code])->assertOk();

    $second = $this->postJson('/api/v1/auth/2fa/verify', ['challenge_token' => $challengeToken, 'code' => $code]);

    $second->assertUnprocessable()->assertJsonPath('success', false);
});
