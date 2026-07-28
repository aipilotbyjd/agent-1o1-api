<?php

use App\Models\Billing\Plan;
use App\Models\User;

it('lists only active plans ordered by sort order', function () {
    $user = User::factory()->create();
    Plan::factory()->create(['name' => 'Growth', 'sort_order' => 2, 'is_active' => true]);
    Plan::factory()->create(['name' => 'Starter', 'sort_order' => 1, 'is_active' => true]);
    Plan::factory()->create(['name' => 'Retired', 'sort_order' => 0, 'is_active' => false]);

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/plans');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Starter', 'Growth']);
});
