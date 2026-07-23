<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('registers a new user and sends a verification email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'ada@example.com');

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);

    Notification::assertSentTo(User::firstWhere('email', 'ada@example.com'), VerifyEmail::class);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors('email');
});

it('rejects registration with a weak password', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
});
