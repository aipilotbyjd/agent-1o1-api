<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifies email via a valid signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('v1.auth.verify-email', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $response = $this->getJson($url);

    $response->assertOk()->assertJsonPath('success', true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects verification with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('v1.auth.verify-email', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $response = $this->getJson($url);

    $response->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired verification link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('v1.auth.verify-email', now()->subMinute(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson($url)->assertForbidden();
});

it('resends the verification email for an authenticated unverified user', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->postJson('/api/v1/auth/verify-email/resend');

    $response->assertOk()->assertJsonPath('success', true);
    Notification::assertSentTo($user, VerifyEmail::class);
});
