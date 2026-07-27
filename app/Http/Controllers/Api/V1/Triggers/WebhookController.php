<?php

namespace App\Http\Controllers\Api\V1\Triggers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Triggers\Trigger;
use App\Services\Triggers\TriggerEventRecorder;
use App\Services\Triggers\TriggerFiringService;
use App\Services\Triggers\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        public TriggerFiringService $firing,
        public WebhookSignatureVerifier $signatures,
        public TriggerEventRecorder $events,
    ) {}

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $trigger = Trigger::query()
            ->where('token', $token)
            ->where('type', 'webhook')
            ->where('is_active', true)
            ->first();

        if ($trigger === null) {
            return ApiResponse::notFound();
        }

        $lock = Cache::lock("trigger:{$trigger->id}:webhook", 10);

        return $lock->block(3, fn (): JsonResponse => $this->handle($trigger, $request));
    }

    private function handle(Trigger $trigger, Request $request): JsonResponse
    {
        if (! $this->signatures->verify($trigger, $request)) {
            $this->events->record($trigger, 'webhook', false, request: $request, error: 'Invalid signature');

            return ApiResponse::error('Invalid webhook signature.', Response::HTTP_UNAUTHORIZED);
        }

        $deliveryId = $this->firing->resolveDeliveryId($trigger, $request);

        if ($this->isRetriedDelivery($trigger, $request, $deliveryId)) {
            $this->events->record($trigger, 'webhook', false, request: $request, deliveryId: $deliveryId, error: 'Duplicate delivery');

            return ApiResponse::success(null, 'Duplicate delivery ignored.');
        }

        // A 200 (not an error) so providers sending mixed event streams don't retry.
        if (! $this->firing->matchesFilters($trigger, $request->all(), $request->headers->all())) {
            $this->events->record($trigger, 'webhook', false, request: $request, deliveryId: $deliveryId);

            return ApiResponse::success(null, 'Event ignored by trigger filters.');
        }

        if ($this->firing->hasInFlightRun($trigger)) {
            $this->events->record($trigger, 'webhook', false, request: $request, deliveryId: $deliveryId, error: 'Run already in progress');

            return ApiResponse::error('A run is already in progress for this trigger target.', Response::HTTP_CONFLICT);
        }

        try {
            $run = $this->firing->fire($trigger, $request->all(), 'webhook');
        } catch (Throwable $e) {
            $this->events->record($trigger, 'webhook', false, request: $request, deliveryId: $deliveryId, error: $e->getMessage());
            report($e);

            return ApiResponse::error('This trigger failed to start a run.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($run === null) {
            $this->events->record($trigger, 'webhook', false, request: $request, deliveryId: $deliveryId, error: 'Target not runnable');

            return ApiResponse::error('This trigger is not currently runnable.', Response::HTTP_CONFLICT);
        }

        $this->events->record($trigger, 'webhook', true, run: $run, request: $request, deliveryId: $deliveryId);

        return ApiResponse::success(['run_id' => $run->id], 'Run started.', Response::HTTP_ACCEPTED);
    }

    /**
     * Slack has no stable delivery id — fall back to its retry-num header as
     * a heuristic: if this is a marked retry and we already recorded a match
     * for this trigger recently, treat it as already handled.
     */
    private function isRetriedDelivery(Trigger $trigger, Request $request, ?string $deliveryId): bool
    {
        if ($this->firing->isDuplicateDelivery($trigger, $deliveryId)) {
            return true;
        }

        if ($request->header('X-Slack-Retry-Num') === null) {
            return false;
        }

        return $trigger->triggerEvents()
            ->where('matched', true)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();
    }
}
