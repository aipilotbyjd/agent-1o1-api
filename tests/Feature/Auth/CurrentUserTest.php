<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('returns the authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->getJson('/api/v1/auth/user');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

it('updates the authenticated user name without touching verification', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->putJson('/api/v1/auth/user', ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('resets verification when the email is changed', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->putJson('/api/v1/auth/user', ['email' => 'new@example.com']);

    $response->assertOk()->assertJsonPath('data.email', 'new@example.com');
    expect($user->fresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user->fresh(), VerifyEmail::class);
});

it('deletes the authenticated user account and revokes tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $response = $this->withToken($token->accessToken)->deleteJson('/api/v1/auth/user');

    $response->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
