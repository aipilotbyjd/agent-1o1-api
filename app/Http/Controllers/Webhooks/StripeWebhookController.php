<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Billing\CreditPackStatus;
use App\Models\Billing\CreditPack;
use App\Models\Billing\Plan;
use App\Models\Billing\ProcessedWebhookEvent;
use App\Notifications\Billing\PaymentFailedNotification;
use App\Services\Billing\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierWebhookController
{
    /**
     * Guards every event with an idempotency check before Cashier (or our own handlers)
     * process it — Stripe retries webhook delivery, and without this, a redelivered
     * checkout.session.completed would grant credits twice.
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if ($eventId === null) {
            return $this->missingMethod($payload ?? []);
        }

        $isNewEvent = DB::transaction(function () use ($eventId, $payload) {
            if (ProcessedWebhookEvent::query()->where('stripe_event_id', $eventId)->lockForUpdate()->exists()) {
                return false;
            }

            ProcessedWebhookEvent::create([
                'stripe_event_id' => $eventId,
                'type' => $payload['type'] ?? 'unknown',
                'processed_at' => now(),
            ]);

            return true;
        });

        if (! $isNewEvent) {
            return new Response('Webhook already processed', 200);
        }

        return parent::handleWebhook($request);
    }

    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $this->syncPlanFromStripePrice($payload);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $this->syncPlanFromStripePrice($payload);

        return $response;
    }

    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $session = $payload['data']['object'];
        $creditPackId = $session['metadata']['credit_pack_id'] ?? null;

        if ($creditPackId === null) {
            return $this->successMethod();
        }

        /** @var CreditPack|null $pack */
        $pack = CreditPack::query()->find($creditPackId);

        if ($pack === null || $pack->status === CreditPackStatus::Completed) {
            return $this->successMethod();
        }

        $pack->update([
            'status' => CreditPackStatus::Completed,
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'purchased_at' => now(),
        ]);

        app(CreditService::class)->grant(
            $pack->workspace,
            $pack->credits_amount,
            $pack,
            "Credit pack purchase: {$pack->pack_key}",
        );

        return $this->successMethod();
    }

    protected function handleInvoicePaymentFailed(array $payload)
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $workspace = $customerId ? Cashier::findBillable($customerId) : null;

        if ($workspace !== null) {
            $workspace->owner->notify(new PaymentFailedNotification($workspace));
        }

        return $this->successMethod();
    }

    private function syncPlanFromStripePrice(array $payload): void
    {
        $customerId = $payload['data']['object']['customer'] ?? null;
        $stripeSubscriptionId = $payload['data']['object']['id'] ?? null;
        $stripePriceId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;

        if ($customerId === null || $stripeSubscriptionId === null || $stripePriceId === null) {
            return;
        }

        $workspace = Cashier::findBillable($customerId);

        if ($workspace === null) {
            return;
        }

        $plan = Plan::query()
            ->where('stripe_price_id_monthly', $stripePriceId)
            ->orWhere('stripe_price_id_yearly', $stripePriceId)
            ->first();

        if ($plan === null) {
            return;
        }

        $workspace->subscriptions()
            ->where('stripe_id', $stripeSubscriptionId)
            ->update(['plan_id' => $plan->id]);
    }
}
