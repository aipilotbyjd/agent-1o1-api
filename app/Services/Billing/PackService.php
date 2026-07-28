<?php

namespace App\Services\Billing;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Config;

class PackService
{
    /**
     * @return array<string, array{credits: int, price_cents: int}>
     */
    public function catalog(): array
    {
        return collect(Config::array('billing.packs'))
            ->map(fn (array $pack): array => [
                'credits' => $pack['credits'],
                'price_cents' => $pack['price_cents'],
            ])
            ->all();
    }

    public function checkout(Workspace $workspace, string $packKey, User $purchaser): string
    {
        $pack = Config::array('billing.packs')[$packKey] ?? null;

        abort_if($pack === null, 404, "Unknown credit pack [{$packKey}].");
        abort_if($pack['stripe_price_id'] === null, 422, "Credit pack [{$packKey}] has no Stripe price configured.");

        $record = CreditPack::create([
            'workspace_id' => $workspace->id,
            'purchased_by' => $purchaser->id,
            'pack_key' => $packKey,
            'credits_amount' => $pack['credits'],
            'price_cents' => $pack['price_cents'],
            'currency' => 'usd',
            'status' => CreditPackStatus::Pending,
        ]);

        $checkout = $workspace->checkout([$pack['stripe_price_id'] => 1], [
            'success_url' => $this->successUrl($workspace),
            'cancel_url' => $this->cancelUrl($workspace),
            'metadata' => ['credit_pack_id' => $record->id],
        ]);

        $session = $checkout->asStripeCheckoutSession();

        $record->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }

    private function successUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?pack_checkout=success";
    }

    private function cancelUrl(Workspace $workspace): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/workspaces/{$workspace->slug}/billing?pack_checkout=cancel";
    }
}
