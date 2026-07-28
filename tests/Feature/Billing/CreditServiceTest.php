<?php

use App\Exceptions\Billing\InsufficientCreditsException;
use App\Models\Billing\UsagePeriod;
use App\Models\Workspaces\Workspace;
use App\Services\Billing\CreditService;

it('consumes credits and records a transaction', function () {
    $workspace = Workspace::factory()->create();
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 100,
        'credits_used' => 0,
    ]);

    $transaction = app(CreditService::class)->consume($workspace, 30);

    expect($period->fresh()->credits_used)->toBe(30)
        ->and($period->fresh()->executions_total)->toBe(1)
        ->and($transaction->amount)->toBe(-30)
        ->and($transaction->balance_after)->toBe(70);
});

it('throws when consuming more credits than remain', function () {
    $workspace = Workspace::factory()->create();
    UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 10,
        'credits_used' => 5,
    ]);

    app(CreditService::class)->consume($workspace, 10);
})->throws(InsufficientCreditsException::class);

it('does not overspend across sequential consume calls at the boundary', function () {
    $workspace = Workspace::factory()->create();
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 10,
        'credits_used' => 0,
    ]);

    app(CreditService::class)->consume($workspace, 6);

    expect(fn () => app(CreditService::class)->consume($workspace, 6))
        ->toThrow(InsufficientCreditsException::class);

    expect($period->fresh()->credits_used)->toBe(6);
});

it('bypasses the balance check for unlimited plans', function () {
    $workspace = Workspace::factory()->create();
    UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => -1,
        'credits_used' => 0,
    ]);

    app(CreditService::class)->consume($workspace, 100000);

    expect(true)->toBeTrue();
});

it('grants credits from a pack purchase', function () {
    $workspace = Workspace::factory()->create();
    $period = UsagePeriod::factory()->create([
        'workspace_id' => $workspace->id,
        'credits_limit' => 100,
        'credits_used' => 100,
        'credits_from_packs' => 0,
    ]);

    app(CreditService::class)->grant($workspace, 500);

    expect($period->fresh()->credits_from_packs)->toBe(500)
        ->and($period->fresh()->creditsRemaining())->toBe(500);
});
