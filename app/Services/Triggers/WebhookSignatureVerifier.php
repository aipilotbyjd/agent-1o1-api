<?php

namespace App\Services\Triggers;

use App\Models\Triggers\Trigger;
use Illuminate\Http\Request;

class WebhookSignatureVerifier
{
    /**
     * Verify an inbound webhook request against the trigger's configured
     * signature scheme. Passes when no scheme or secret is configured —
     * signing is opt-in per trigger type.
     */
    public function verify(Trigger $trigger, Request $request): bool
    {
        $scheme = $trigger->triggerType?->signature_scheme;
        $secret = $trigger->signing_secret;

        if ($scheme === null || $secret === null) {
            return true;
        }

        return match ($scheme) {
            'github' => $this->verifyGithub($request, $secret),
            'stripe' => $this->verifyStripe($request, $secret),
            'slack' => $this->verifySlack($request, $secret),
            default => false,
        };
    }

    private function verifyGithub(Request $request, string $secret): bool
    {
        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    private function verifyStripe(Request $request, string $secret): bool
    {
        $header = (string) $request->header('Stripe-Signature', '');
        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        return hash_equals($expected, $signature);
    }

    private function verifySlack(Request $request, string $secret): bool
    {
        $timestamp = (string) $request->header('X-Slack-Request-Timestamp', '');
        $signature = (string) $request->header('X-Slack-Signature', '');

        if ($timestamp === '' || ! str_starts_with($signature, 'v0=')) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$request->getContent()}", $secret);

        return hash_equals($expected, $signature);
    }
}
