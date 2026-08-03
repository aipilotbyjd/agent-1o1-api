<?php

use App\Enums\NotificationEvent;
use App\Models\User;

it('lists every toggleable notification event', function () {
    $user = User::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/notifications/events');

    $response->assertOk()
        ->assertJsonCount(count(NotificationEvent::cases()), 'data')
        ->assertJsonStructure([
            'data' => [
                ['key', 'label', 'description', 'defaults' => ['in_app', 'email']],
            ],
        ]);
});

it('exposes every event the notification classes can fire', function () {
    $user = User::factory()->create();

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/notifications/events');

    expect(array_column($response->json('data'), 'key'))
        ->toEqualCanonicalizing(array_column(NotificationEvent::cases(), 'value'));
});

it('requires authentication', function () {
    $this->getJson('/api/v1/notifications/events')->assertUnauthorized();
});
