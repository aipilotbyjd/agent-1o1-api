<?php

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;

function checkoutSessionCompletedPayload(string $eventId, string $customerId, int $creditPackId, string $sessionId): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => $sessionId,
                'customer' => $customerId,
                'payment_intent' => 'pi_test_123',
                'metadata' => ['credit_pack_id' => (string) $creditPackId],
            ],
        ],
    ];
}

it('fulfills a credit pack exactly once even if the webhook is redelivered', function () {
    $workspace = Workspace::factory()->create(['stripe_id' => 'cus_test_123']);
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 1000,
        'credits_from_packs' => 0,
    ]);
    $pack = CreditPack::factory()->create([
        'workspace_id' => $workspace->id,
        'status' => CreditPackStatus::Pending,
        'credits_amount' => 500,
    ]);

    $payload = checkoutSessionCompletedPayload('evt_test_1', 'cus_test_123', $pack->id, 'cs_test_1');

    $this->postJson('/stripe/webhook', $payload)->assertOk();

    expect($pack->fresh()->status)->toBe(CreditPackStatus::Completed)
        ->and($period->fresh()->credits_from_packs)->toBe(500);

    // Stripe redelivers the same event — must not grant credits twice.
    $this->postJson('/stripe/webhook', $payload)->assertOk();

    expect($period->fresh()->credits_from_packs)->toBe(500);
});
