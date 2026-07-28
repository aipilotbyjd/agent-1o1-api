<?php

use App\Models\Nodes\NodeCategory;
use App\Models\User;

it('lists node categories ordered by sort order', function () {
    $user = User::factory()->create();
    NodeCategory::factory()->create(['name' => 'Second', 'sort_order' => 2]);
    NodeCategory::factory()->create(['name' => 'First', 'sort_order' => 1]);

    $response = $this->withToken(authHeader($user))->getJson('/api/v1/node-categories');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('First');
});
