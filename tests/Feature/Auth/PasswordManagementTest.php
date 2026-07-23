<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a password reset link for an existing email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ada@example.com']);

    $response->assertOk()->assertJsonPath('success', true);
    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns the same generic response for a nonexistent email to avoid enumeration', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

    $response->assertOk()->assertJsonPath('success', true);
    Notification::assertNothingSent();
});

it('resets the password with a valid token and revokes existing tokens', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => Hash::make('OldPassword123!')]);
    $existingToken = $user->createToken('old-session');
    $token = Password::createToken($user);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'ada@example.com',
        'token' => $token,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
    expect($existingToken->token->fresh()->revoked)->toBeTrue();
});

it('rejects reset with an invalid token', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'ada@example.com',
        'token' => 'not-a-real-token',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()->assertJsonPath('success', false);
});

it('changes the password for the authenticated user', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->putJson('/api/v1/auth/password/change', [
        'current_password' => 'OldPassword123!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue();
});

it('rejects changing password with an incorrect current password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->putJson('/api/v1/auth/password/change', [
        'current_password' => 'WrongPassword!',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('current_password');
});
